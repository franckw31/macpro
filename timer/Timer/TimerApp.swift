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

@main
struct TimerApp: App {
    #if canImport(UIKit)
    @UIApplicationDelegateAdaptor(AppDelegate.self) var appDelegate
    #endif
    @StateObject private var viewModel  = PokerTimerViewModel()
    @StateObject private var auth       = AuthService.shared
    private let speechSynthesizer = AVSpeechSynthesizer()

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
                do {
                    let audioSession = AVAudioSession.sharedInstance()
                    try audioSession.setCategory(.playback, mode: .spokenAudio, options: [.duckOthers, .mixWithOthers])
                    try audioSession.setActive(true)
                } catch {
                    print("Failed to setup audio session for welcome message: \(error)")
                }
                
                let utterance = AVSpeechUtterance(string: "Bienvenue sur Carde Ivènte")
                utterance.voice = AVSpeechSynthesisVoice(language: "fr-FR")
                speechSynthesizer.speak(utterance)
            }
            .task {
                await auth.autoLogin()
            }
        }
    }
}

// MARK: - MainTabView
private struct MainTabView: View {
    @ObservedObject var viewModel: PokerTimerViewModel
    @State private var selection: Int = 0

    var body: some View {
        TabView(selection: $selection) {
            HomeView(viewModel: viewModel, showPrizepool: false)
                .tag(0)
                .tabItem {
                    Label("Accueil", systemImage: "house.fill")
                }

            ContentView(viewModel: viewModel)
                .tag(1)
                .tabItem {
                    Label("Local Timer", systemImage: "timer")
                }

            HomeView(viewModel: viewModel, showPrizepool: true)
                .tag(2)
                .tabItem {
                    Label("Répartition", systemImage: "eurosign.circle")
                }
        }
        .tint(Color(red: 0, green: 0.82, blue: 1))
    }
}

// MARK: - PrizepoolView (onglet Répartition)
private struct PrizepoolView: View {
    @State private var prizepoolInput: String = ""
    @State private var buyinsInput: String = ""
    @State private var repartition: [(place: Int, gain: Int)] = []
    @State private var showResult: Bool = false

    private let cyan = Color(red: 0, green: 0.82, blue: 1)
    private let gold = Color(red: 1, green: 0.84, blue: 0)

    var body: some View {
        NavigationStack {
            ScrollView {
                VStack(alignment: .leading, spacing: 14) {
                    Label("Répartition du Prizepool", systemImage: "eurosign.circle").font(.caption.bold()).foregroundColor(cyan).textCase(.uppercase)
                    Divider().background(cyan.opacity(0.3))
                    HStack(spacing:12) {
                        VStack(alignment:.leading, spacing:4) { Text("Pricepool").font(.caption2).foregroundColor(.white); TextField("Ex: 200", text: $prizepoolInput).keyboardType(.numberPad).frame(width:100).padding(8).background(Color.white.opacity(0.06)).cornerRadius(8).foregroundColor(.white) }
                        VStack(alignment:.leading, spacing:4) { Text("Nb Buy-Rebuy").font(.caption2).foregroundColor(.white); TextField("Ex: 20", text: $buyinsInput).keyboardType(.numberPad).frame(width:80).padding(8).background(Color.white.opacity(0.06)).cornerRadius(8).foregroundColor(.white) }
                        Spacer()
                        Button { calculateRepartition() } label: { Text("Calculer").font(.subheadline.bold()).foregroundColor(.white).padding(.horizontal,14).padding(.vertical,8).background(cyan.opacity(0.85)).cornerRadius(8) }.disabled(prizepoolInput.isEmpty || buyinsInput.isEmpty)
                    }

                    if showResult && !repartition.isEmpty {
                        VStack(alignment:.leading, spacing:6) {
                            Text("Répartition proposée :").font(.caption.bold()).foregroundColor(.secondary)
                            ForEach(repartition, id: \.place) { r in
                                HStack { Text("\(r.place)\(ordinalSuffix(r.place)) :").font(.subheadline).foregroundColor(.white); Spacer(); Text("\(r.gain) €").font(.subheadline.bold()).foregroundColor(gold) }
                            }
                        }
                    }
                }
                .padding(16)
            }
            .background(Color.black.ignoresSafeArea())
            .navigationTitle("Répartition")
        }
    }

    private func ordinalSuffix(_ n: Int) -> String { n == 1 ? "er" : "e" }

    private func calculateRepartition() {
        hideKeyboard()
        guard let prizepool = Int(prizepoolInput), let buyins = Int(buyinsInput), prizepool > 0, buyins > 0 else { repartition = []; showResult = false; return }

        // decide number of paid places
        let places: Int
        if buyins > 24 { places = 5 }
        else if buyins >= 18 { places = 4 }
        else if buyins >= 10 { places = 3 }
        else { places = 2 }
        let percents: [Int] = (places == 5) ? [35,25,18,12,10] : (places == 4) ? [40,30,20,10] : (places==3 ? [50,30,20] : [65,35])

        // target total rounded down to nearest 10 to ensure payouts are multiples of 10
        let targetTotal = (prizepool / 10) * 10

        // compute raw gains and floor each to nearest lower multiple of 10
        let raw = percents.map { Double(prizepool) * Double($0) / 100.0 }
        var gains = raw.map { Int(floor($0 / 10.0)) * 10 }

        // distribute remaining tens starting from the top place
        var currentSum = gains.reduce(0, +)
        var remaining = targetTotal - currentSum
        var idx = 0
        while remaining > 0 && !gains.isEmpty {
            gains[idx % gains.count] += 10
            remaining -= 10
            idx += 1
        }

        // give a bit less to the last place and a bit more to the second place
        if gains.count >= 3 {
            let lastIdx = gains.count - 1
            if gains[lastIdx] >= 10 {
                gains[lastIdx] -= 10
                gains[1] += 10
            }
        }

        repartition = Array(zip(1...places, gains))
        showResult = true
    }

    private func hideKeyboard() {
        #if canImport(UIKit)
        UIApplication.shared.sendAction(#selector(UIResponder.resignFirstResponder), to: nil, from: nil, for: nil)
        #endif
    }
}

// MARK: - SplashView (pendant vérification du token)
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
                ProgressView()
                    .tint(cyan)
            }
        }
    }
}
