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

    // Token APNs reçu depuis Apple
    func application(
        _ application: UIApplication,
        didRegisterForRemoteNotificationsWithDeviceToken deviceToken: Data
    ) {
        let token = deviceToken.map { String(format: "%02.2hhx", $0) }.joined()
        print("APNs device token: \(token)")
        UserDefaults.standard.set(token, forKey: "cardevent.deviceToken")
        Self.registerTokenWithServer(token)
    }

    func application(
        _ application: UIApplication,
        didFailToRegisterForRemoteNotificationsWithError error: Error
    ) {
        print("APNs registration failed: \(error)")
    }

    // Afficher la notification même quand l'app est au premier plan
    func userNotificationCenter(
        _ center: UNUserNotificationCenter,
        willPresent notification: UNNotification,
        withCompletionHandler completionHandler: @escaping (UNNotificationPresentationOptions) -> Void
    ) {
        completionHandler([.banner, .sound])
    }

    // Envoie le token à notre serveur
    static func registerTokenWithServer(_ token: String) {
        guard let url = URL(string: "https://viendez.com/api/register-device.php") else { return }
        var request = URLRequest(url: url)
        request.httpMethod = "POST"
        request.setValue("application/json", forHTTPHeaderField: "Content-Type")
        request.timeoutInterval = 10
        let payload = ["device_token": token]
        request.httpBody = try? JSONSerialization.data(withJSONObject: payload)
        URLSession.shared.dataTask(with: request) { data, _, error in
            if let error = error {
                print("Token registration error: \(error)")
            } else {
                print("Token registered with server")
            }
        }.resume()
    }
}
#endif

@MainActor
private final class WelcomeSpeaker: NSObject, AVSpeechSynthesizerDelegate {
    private let speechSynthesizer = AVSpeechSynthesizer()

    override init() {
        super.init()
        speechSynthesizer.delegate = self
    }

    func speakWelcomeMessage() {
        do {
            let audioSession = AVAudioSession.sharedInstance()
            try audioSession.setCategory(.ambient, mode: .default, options: [.mixWithOthers])
            try audioSession.setActive(true)
        } catch {
            print("Failed to setup audio session for welcome message: \(error)")
        }

        let utterance = AVSpeechUtterance(string: "Bienvenue sur Carde Ivènte")
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
        do {
            try AVAudioSession.sharedInstance().setActive(false, options: .notifyOthersOnDeactivation)
        } catch {
            print("Failed to release audio session after welcome message: \(error)")
        }
    }
}

// MARK: - Notification name

extension Notification.Name {
    static let cardEventEmailVerified = Notification.Name("cardevent.emailVerified")
}

@main
struct CardEventApp: App {
    #if canImport(UIKit)
    @UIApplicationDelegateAdaptor(AppDelegate.self) var appDelegate
    #endif
    @StateObject private var viewModel  = PokerTimerViewModel()
    @StateObject private var auth       = AuthService.shared
    private let welcomeSpeaker = WelcomeSpeaker()

    var body: some Scene {
        WindowGroup {
            Group {
                if auth.isAuthenticated {
                    MainTabView(viewModel: viewModel)
                        .transition(.opacity)
                } else if auth.isLoading {
                    SplashView()
                        .transition(.opacity)
                } else {
                    LoginView(auth: auth)
                        .transition(.opacity)
                }
            }
            .animation(.easeInOut(duration: 0.3), value: auth.isAuthenticated)
            .animation(.easeInOut(duration: 0.3), value: auth.isLoading)
            .onAppear {
                welcomeSpeaker.speakWelcomeMessage()
            }
            .task {
                await auth.autoLogin()
            }
            .onOpenURL { url in
                if url.scheme == "cardevent", url.host == "email-verified" {
                    NotificationCenter.default.post(name: .cardEventEmailVerified, object: nil)
                }
            }
        }
    }
}

// small shared MainTabView and SplashView kept as in original
private struct MainTabView: View {
    @ObservedObject var viewModel: PokerTimerViewModel
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
        }
        .tint(Color(red: 0, green: 0.82, blue: 1))
    }
}

private struct SplashView: View {
    private let cyan = Color(red: 0, green: 0.82, blue: 1)
    var body: some View {
        ZStack {
            Color.black.ignoresSafeArea()
            VStack(spacing: 20) {
                Image(systemName: "suit.spade.fill")
                    .font(.system(size: 64))
                    .foregroundColor(cyan)
                    .shadow(color: cyan.opacity(0.6), radius: 16)
                ProgressView().tint(cyan)
            }
        }
    }
}
