import SwiftUI
import UserNotifications
import AVFoundation

#if canImport(UIKit)
import UIKit

class AppDelegate: NSObject, UIApplicationDelegate, UNUserNotificationCenterDelegate {

    func application(
        _ application: UIApplication,
        didFinishLaunchingWithOptions launchOptions: [UIApplication.LaunchOptionsKey: Any]? = nil
    ) -> Bool {
        UNUserNotificationCenter.current().delegate = self
        return true
    }

    func application(
        _ application: UIApplication,
        didRegisterForRemoteNotificationsWithDeviceToken deviceToken: Data
    ) {
        let token = deviceToken.map { String(format: "%02.2hhx", $0) }.joined()
        print("APNs device token (Pro): \(token)")
        UserDefaults.standard.set(token, forKey: "cardeventpro.deviceToken")
        Self.registerTokenWithServer(token)
    }

    func application(
        _ application: UIApplication,
        didFailToRegisterForRemoteNotificationsWithError error: Error
    ) {
        print("APNs registration failed: \(error)")
    }

    func userNotificationCenter(
        _ center: UNUserNotificationCenter,
        willPresent notification: UNNotification,
        withCompletionHandler completionHandler: @escaping (UNNotificationPresentationOptions) -> Void
    ) {
        completionHandler([.banner, .sound])
    }

    static func registerTokenWithServer(_ token: String) {
        guard let url = URL(string: "https://viendez.com/api/register-device.php") else { return }
        var request = URLRequest(url: url)
        request.httpMethod = "POST"
        request.setValue("application/json", forHTTPHeaderField: "Content-Type")
        request.timeoutInterval = 10
        let payload = ["device_token": token, "app": "pro"]
        request.httpBody = try? JSONSerialization.data(withJSONObject: payload)
        URLSession.shared.dataTask(with: request) { _, _, error in
            if let error = error {
                print("Token registration error: \(error)")
            } else {
                print("Pro token registered with server")
            }
        }.resume()
    }
}
#endif

private final class WelcomeSpeaker: NSObject, AVSpeechSynthesizerDelegate, @unchecked Sendable {
    private let speechSynthesizer = AVSpeechSynthesizer()

    override init() {
        super.init()
        speechSynthesizer.delegate = self
    }

    func speakWelcomeMessage() {
        #if os(iOS)
        do {
            let audioSession = AVAudioSession.sharedInstance()
            try audioSession.setCategory(.ambient, mode: .default, options: [.mixWithOthers])
            try audioSession.setActive(true)
        } catch {
            print("Failed to setup audio session: \(error)")
        }
        #endif
        let utterance = AVSpeechUtterance(string: "Bienvenue sur Carde Ivènte Pro")
        utterance.voice = AVSpeechSynthesisVoice(language: "fr-FR")
        speechSynthesizer.speak(utterance)
    }

    func speechSynthesizer(_ synthesizer: AVSpeechSynthesizer, didFinish utterance: AVSpeechUtterance) {
        releaseAudioSession()
    }
    func speechSynthesizer(_ synthesizer: AVSpeechSynthesizer, didCancel utterance: AVSpeechUtterance) {
        releaseAudioSession()
    }
    private func releaseAudioSession() {
        #if os(iOS)
        try? AVAudioSession.sharedInstance().setActive(false, options: .notifyOthersOnDeactivation)
        #endif
    }
}

@main
struct CardEventProApp: App {
    #if canImport(UIKit)
    @UIApplicationDelegateAdaptor(AppDelegate.self) var appDelegate
    #endif
    @StateObject private var viewModel = PokerTimerViewModel()
    @StateObject private var auth      = AuthService.shared
    private let welcomeSpeaker = WelcomeSpeaker()

    var body: some Scene {
        WindowGroup {
            Group {
                if auth.isAuthenticated {
                    ProMainTabView(viewModel: viewModel)
                        .transition(.opacity)
                } else if auth.isLoading {
                    ProSplashView()
                        .transition(.opacity)
                } else {
                    LoginView(auth: auth)
                        .transition(.opacity)
                }
            }
            .environmentObject(auth)
            .animation(.easeInOut(duration: 0.3), value: auth.isAuthenticated)
            .animation(.easeInOut(duration: 0.3), value: auth.isLoading)
            .onAppear {
                welcomeSpeaker.speakWelcomeMessage()
            }
            .task {
                await auth.autoLogin()
            }
        }
    }
}

// MARK: - Pro Main Tab View
private struct ProMainTabView: View {
    @ObservedObject var viewModel: PokerTimerViewModel
    @EnvironmentObject var auth: AuthService
    @State private var selection: Int = 0

    var body: some View {
        TabView(selection: $selection) {
            HomeView(viewModel: viewModel, showPrizepool: false)
                .tag(0)
                .tabItem { Label("Accueil", systemImage: "house.fill") }

            ContentView(viewModel: viewModel)
                .tag(1)
                .tabItem { Label("Local Timer", systemImage: "timer") }

            HomeView(viewModel: viewModel, showPrizepool: true)
                .tag(2)
                .tabItem { Label("Répartition", systemImage: "eurosign.circle") }

            // ---- Onglet exclusif Pro ----
            OrganizerDashboardView()
                .tag(3)
                .tabItem { Label("Organisateur", systemImage: "person.badge.plus") }
        }
        .tint(Color(red: 1.0, green: 0.75, blue: 0.0)) // Or pour Pro
        .onAppear {
            // Inject auth into environment for child views
        }
        .environmentObject(auth)
    }
}

// MARK: - Pro Splash Screen
private struct ProSplashView: View {
    private let gold = Color(red: 1.0, green: 0.75, blue: 0.0)
    var body: some View {
        ZStack {
            Color.black.ignoresSafeArea()
            VStack(spacing: 20) {
                ZStack {
                    Image(systemName: "suit.spade.fill")
                        .font(.system(size: 64))
                        .foregroundColor(gold)
                        .shadow(color: gold.opacity(0.8), radius: 20)
                    Image(systemName: "star.fill")
                        .font(.system(size: 18))
                        .foregroundColor(gold)
                        .offset(x: 28, y: -28)
                }
                Text("CardEvent Pro")
                    .font(.largeTitle.bold())
                    .foregroundColor(gold)
                ProgressView().tint(gold)
            }
        }
    }
}
