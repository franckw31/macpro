
import Foundation
import Combine
import AVFoundation
import UserNotifications
#if canImport(UIKit)
import UIKit
#endif

// MARK: - Background Audio Keep Alive

private final class BackgroundAudioKeepAlive {

    static let shared = BackgroundAudioKeepAlive()

    private let engine = AVAudioEngine()
    private var isStarted = false

    private init() {}

    func start() {
        guard !isStarted else { return }
        do {
            let session = AVAudioSession.sharedInstance()
            try session.setCategory(.playback, mode: .default, options: .mixWithOthers)
            try session.setActive(true)
        } catch {
            print("BackgroundAudio: session error: \(error)")
            return
        }
        let format = AVAudioFormat(standardFormatWithSampleRate: 44100, channels: 1)!
        let sourceNode = AVAudioSourceNode(format: format) { _, _, frameCount, audioBufferList in
            let ptr = UnsafeMutableAudioBufferListPointer(audioBufferList)
            for buffer in ptr {
                memset(buffer.mData, 0, Int(frameCount) * MemoryLayout<Float>.size)
            }
            return noErr
        }
        engine.attach(sourceNode)
        engine.connect(sourceNode, to: engine.mainMixerNode, format: format)
        engine.mainMixerNode.outputVolume = 0.0
        do {
            try engine.start()
            isStarted = true
            print("BackgroundAudio: started (silence)")
        } catch {
            print("BackgroundAudio: engine error: \(error)")
        }
    }

    func stop() {
        guard isStarted else { return }
        engine.stop()
        isStarted = false
        try? AVAudioSession.sharedInstance().setActive(false, options: .notifyOthersOnDeactivation)
        print("BackgroundAudio: stopped")
    }
}

// MARK: - PokerTimerViewModel

// MARK: - Participant
struct Participant: Identifiable {
    let id = UUID()
    let pseudo: String
    let statut: String
    let latereg: Int?
    let recave: Int
    let jetonsTotal: Int
    let isMe: Bool
    let dateInscription: String
    let bonus1: Int
    let classement: Int
    let gain: Int
    let bounty: Int
    let challengeRank: Int

    var statutColor: String {
        switch statut {
        case "Présent", "Present":  return "green"
        case "Inscrit", "Réservation", "Reservation":             return "blue"
        case "Confirmé", "Confirme": return "cyan"
        case "Option":              return "yellow"
        case "Eliminé", "Elimine": return "red"
        default:                    return "gray"
        }
    }
}

// MARK: - ActivitySummary
struct ActivitySummary: Identifiable {
    let id: Int
    let date: String        // formaté "Lun 16"
    let dateFull: String    // formaté "Lundi 16 Mars"
    let title: String
    let buyin: Int
    let rake: Int
    let recave_montant: Int
    let count: Int
    let recaves: Int
    let organisateur: String
    let startDate: Date
}

@MainActor
final class PokerTimerViewModel: ObservableObject {
    @Published var blindLevels: [BlindLevel] = []
    @Published var currentLevelIndex: Int = 0
    @Published var timeLeft: Int = 0
    @Published var isRunning: Bool = false
    @Published var alertMessage: String?
    @Published var playerCount: Int = 6
    @Published var nextActivityDate: String?
    @Published var nextActivityDateFull: String?
    @Published var nextActivityBuyin: Int?
    @Published var nextActivityRake: Int?
    @Published var nextActivityId: Int?
    @Published var nextActivityStart: Date?
    @Published var isRegistered: Bool = false
    @Published var isRegistering: Bool = false
    
    // Save local registration options
    @Published var localRegAnonyme: Bool = false
    @Published var localRegOption: Bool = false
    @Published var localRegLatereg: Bool = false
    
    @Published var participants: [Participant] = []
    @Published var isFetchingParticipants: Bool = false
    @Published var currentStructureName: String = ""
    @Published var activityList: [ActivitySummary] = []
    @Published var selectedActivityIndex: Int = 0
    @Published var activityInfo: ActivityInfo? = nil
    @Published var isFetchingActivityInfo: Bool = false

    private var cardevent: Timer?
    private var pollingTimer: Timer?
    private var levelEndDate: Date?

    private let levelsKey = "cardevent.blindLevels"
    private let stateKey = "cardevent.state"
    private let playerCountKey = "cardevent.playerCount"
    private let lastPlayerCountKey = "cardevent.lastPlayerCount"
    private let structureNameKey = "cardevent.structureName"
    private let apiBaseURL = "https://viendez.com/api/players-count.php"
    private let activityAPIURL = "https://viendez.com/api/next-activity.php"
    private let registerAPIURL = "https://viendez.com/api/register-activity-v4.php"
    private let participantsAPIURL = "https://viendez.com/api/participants-list.php"
    private let activitiesListURL  = "https://viendez.com/api/activities-list.php"
    private let activityInfoURL    = "https://viendez.com/api/activity-info.php"
    private let logAPIURL = "https://viendez.com/api/log-usage.php"
    private var isManualNavigation: Bool = false
    
    // Note: registerAPIURL points to an existing endpoint on the server
    // Changed from register-activity-noauth.php (doesn't exist on server) to register-activity2.php

    private struct TimerState: Codable {
        let currentLevelIndex: Int
        let isRunning: Bool
        let levelEndDate: Date?      // date absolue de fin du niveau (si en cours)
        let timeLeftWhenPaused: Int  // temps restant (si en pause)
    }

    init() {
        loadLevels()
        loadTimerState()
        loadPlayerCount()
        currentStructureName = UserDefaults.standard.string(forKey: "cardevent.structureName") ?? ""
        requestNotificationPermission()
        fetchNextActivity()
        fetchActivityList()
        startPolling()
        setupBackgroundHandling()
        logAppUsage()
    }

    deinit {
        cardevent?.invalidate()
        pollingTimer?.invalidate()
    }

    var currentLevel: BlindLevel {
        blindLevels[safe: currentLevelIndex] ?? BlindLevel(level: 1, smallBlind: 0, bigBlind: 0, ante: 0, duration: 900)
    }

    var nextLevel: BlindLevel? {
        blindLevels[safe: currentLevelIndex + 1]
    }

    var formattedTime: String {
        let safeTime = max(0, timeLeft)
        let minutes = safeTime / 60
        let seconds = safeTime % 60
        return String(format: "%02d:%02d", minutes, seconds)
    }

    var nextBlindText: String {
        guard let next = nextLevel else { return "Tournament End" }
        return "\(next.smallBlind)/\(next.bigBlind)"
    }

    /// Retourne "Pause dans X min" si une pause (smallBlind==0 && bigBlind==0) est à venir,
    /// nil si on est déjà en pause ou s'il n'y en a pas.
    var minutesUntilBreakText: String? {
        // Si le niveau actuel est déjà une pause, ne rien afficher
        guard !(currentLevel.smallBlind == 0 && currentLevel.bigBlind == 0) else { return nil }
        var totalSeconds = timeLeft
        for idx in (currentLevelIndex + 1)..<blindLevels.count {
            let lvl = blindLevels[idx]
            if lvl.smallBlind == 0 && lvl.bigBlind == 0 {
                let minutes = Int(ceil(Double(totalSeconds) / 60.0))
                return "Pause dans \(minutes) min"
            }
            totalSeconds += lvl.duration
        }
        return nil
    }

    func toggleStartPause() {
        isRunning ? pause() : start()
    }

    func start() {
        guard !isRunning else { return }
        isRunning = true
        levelEndDate = Date().addingTimeInterval(TimeInterval(timeLeft))
        BackgroundAudioKeepAlive.shared.start()
        startTicker()
        scheduleNextLevelNotification()
        saveTimerState()
    }

    func pause() {
        guard isRunning else { return }
        if let endDate = levelEndDate {
            timeLeft = max(0, Int(endDate.timeIntervalSinceNow))
        }
        levelEndDate = nil
        isRunning = false
        cardevent?.invalidate()
        cardevent = nil
        BackgroundAudioKeepAlive.shared.stop()
        let ids = (0..<50).map { "level_\($0)" }
        UNUserNotificationCenter.current().removePendingNotificationRequests(withIdentifiers: ids)
        saveTimerState()
    }

    func resetTournament() {
        pause()
        currentLevelIndex = 0
        timeLeft = max(0, blindLevels.first?.duration ?? 0)
        saveTimerState()
    }

    func restartCurrentBlind() {
        timeLeft = max(0, currentLevel.duration)
        if isRunning {
            levelEndDate = Date().addingTimeInterval(TimeInterval(timeLeft))
            scheduleNextLevelNotification()
        }
        saveTimerState()
    }

    func adjustTime(minutes: Int) {
        guard !isRunning else { return }
        timeLeft = max(0, timeLeft + minutes * 60)
        saveTimerState()
    }

    func changeLevel(by direction: Int) {
        guard !isRunning else { return }
        let newIndex = currentLevelIndex + direction
        guard blindLevels.indices.contains(newIndex) else { return }
        currentLevelIndex = newIndex
        timeLeft = max(0, currentLevel.duration)
        saveTimerState()
    }

    func updateStructure(_ updatedLevels: [BlindLevel], name: String = "") {
        guard !updatedLevels.isEmpty else { return }
        if !name.isEmpty {
            currentStructureName = name
            UserDefaults.standard.set(name, forKey: structureNameKey)
        }
        blindLevels = updatedLevels.enumerated().map { index, level in
            var copy = level
            copy.level = index + 1
            if copy.duration <= 0 { copy.duration = 60 }
            return copy
        }

        currentLevelIndex = min(currentLevelIndex, blindLevels.count - 1)
        timeLeft = max(0, currentLevel.duration)
        saveLevels()
        saveTimerState()
    }
    
    func setPlayerCount(_ count: Int) {
        playerCount = max(2, count)
        savePlayerCount()
    }
    
    func fetchPlayerCountFromAPI() {
        let session = URLSession.shared
        guard let url = URL(string: apiBaseURL) else {
            print("Invalid API URL")
            return
        }
        
        var request = URLRequest(url: url)
        request.timeoutInterval = 5
        
        Task {
            do {
                let (data, response) = try await session.data(for: request)
                
                guard let httpResponse = response as? HTTPURLResponse, httpResponse.statusCode == 200 else {
                    print("API request failed")
                    return
                }
                
                if let json = try JSONSerialization.jsonObject(with: data) as? [String: Any],
                   let success = json["success"] as? Bool, success,
                   let count = json["count"] as? Int {
                    await MainActor.run {
                        self.playerCount = max(2, count)
                        self.savePlayerCount()
                    }
                }
            } catch {
                print("API error: \(error)")
            }
        }
    }
    
    func fetchNextActivity() {
        let session = URLSession.shared
        guard let url = URL(string: activityAPIURL) else {
            print("Invalid Activity API URL")
            return
        }
        
        var request = URLRequest(url: url)
        request.timeoutInterval = 5
        
        Task {
            do {
                let (data, response) = try await session.data(for: request)
                
                print("Activity API Response Code: \(((response as? HTTPURLResponse)?.statusCode ?? 0))")
                
                guard let httpResponse = response as? HTTPURLResponse, httpResponse.statusCode == 200 else {
                    print("Activity API request failed")
                    return
                }
                
                let jsonString = String(data: data, encoding: .utf8) ?? "No data"
                print("Activity API JSON: \(jsonString)")
                
                if let json = try JSONSerialization.jsonObject(with: data) as? [String: Any],
                   let success = json["success"] as? Bool, success {
                    await MainActor.run {
                    guard !self.isManualNavigation else { return }
                        if let dateString = json["date"] as? String {
                            print("Parsing date: \(dateString)")
                            let formatter = DateFormatter()
                            formatter.locale = Locale(identifier: "en_US_POSIX")
                            formatter.dateFormat = "yyyy-MM-dd HH:mm:ss"
                            
                            if let date = formatter.date(from: dateString) {
                                formatter.locale = Locale(identifier: "fr_FR")
                                formatter.dateFormat = "EEE d"
                                let raw = formatter.string(from: date)
                                self.nextActivityDate = raw.prefix(1).uppercased() + raw.dropFirst()
                                formatter.dateFormat = "EEEE d MMMM"
                                let rawFull = formatter.string(from: date)
                                self.nextActivityDateFull = rawFull.split(separator: " ").map { String($0.prefix(1).uppercased() + $0.dropFirst()) }.joined(separator: " ")
                                self.nextActivityStart = date
                                print("Next activity date set to: \(self.nextActivityDate ?? "nil")")
                            } else {
                                print("Failed to parse date")
                                self.nextActivityDate = nil
                                self.nextActivityDateFull = nil
                                self.nextActivityStart = nil
                            }
                        } else {
                            print("No date field in JSON")
                            self.nextActivityDate = nil
                        }
                        
                        if let count = json["participants_count"] as? Int {
                            let previousCount = UserDefaults.standard.integer(forKey: self.lastPlayerCountKey)
                            // Ne notifie pas au premier lancement (previousCount == 0)
                            if previousCount > 0 && count != previousCount {
                                let diff = count - previousCount
                                let emoji = diff > 0 ? "🟢" : "🔴"
                                let action = diff > 0 ? "\(diff) nouvelle(s) inscription(s)" : "\(abs(diff)) désinscription(s)"
                                let title = json["titre-activite"] as? String ?? "Prochaine activité"
                                self.sendNotification(
                                    title: "\(emoji) \(title)",
                                    body: "\(action) — \(count) inscrits au total"
                                )
                            }
                            if count > 0 {
                                UserDefaults.standard.set(count, forKey: self.lastPlayerCountKey)
                            }
                            self.playerCount = max(1, count)
                            self.savePlayerCount()
                            print("Participants count set to: \(count)")
                        }
                        self.nextActivityBuyin = json["buyin"] as? Int
                        self.nextActivityRake = json["rake"] as? Int

                        if let newId = json["id"] as? Int {
                            let changed = self.nextActivityId != newId
                            self.nextActivityId = newId
                            if changed {
                                // Disabled: fetchRegistrationStatus() call to avoid 404 errors on startup
                                Task { await self.fetchRegistrationStatus() }
                            }
                        }
                    }
                }
            } catch {
                print("Activity API error: \(error)")
            }
        }
    }

    /// Récupère le statut d'inscription de l'utilisateur connecté pour l'activité courante.
    func fetchRegistrationStatus() async {
        guard let token = AuthService.shared.token,
              let actId = nextActivityId,
              let url = URL(string: registerAPIURL + "?activity_id=\(actId)&token=\(token)") else { return }
        var request = URLRequest(url: url)
        request.httpMethod = "GET"
        request.setValue("Bearer \(token)", forHTTPHeaderField: "Authorization")
        request.timeoutInterval = 8
        do {
            let (data, response) = try await URLSession.shared.data(for: request)
            guard let http = response as? HTTPURLResponse, http.statusCode == 200 else { return }
            if let json = try? JSONSerialization.jsonObject(with: data) as? [String: Any],
               let success = json["success"] as? Bool, success,
               let registered = json["registered"] as? Bool {
                self.isRegistered = registered
                print("Registration status: \(registered) for activity \(actId)")
            }
        } catch {
            print("fetchRegistrationStatus error: \(error)")
        }
    }

    /// Bascule l'inscription de l'utilisateur (Inscrit ↔ None) pour l'activité courante.
    func toggleRegistration() async {
        guard !isRegistering else { return }
        guard let token = AuthService.shared.token else { alertMessage = "Veuillez vous connecter pour vous inscrire."; return }
        guard let actId = nextActivityId else { alertMessage = "Aucune activité sélectionnée."; return }
        guard let url = URL(string: registerAPIURL + "?activity_id=\(actId)&token=\(token)") else { return }
        var request = URLRequest(url: url)
        request.httpMethod = "POST"
        request.setValue("Bearer \(token)", forHTTPHeaderField: "Authorization")
        request.setValue("application/json", forHTTPHeaderField: "Content-Type")
        request.timeoutInterval = 10
        let payload: [String: Any] = ["action": "toggle", "activity_id": actId, "token": token]
        request.httpBody = try? JSONSerialization.data(withJSONObject: payload)

        print("[TOGGLE] Sending toggleRegistration: action=toggle, actId=\(actId)")

        do {
            let (data, response) = try await URLSession.shared.data(for: request)
            guard let http = response as? HTTPURLResponse else {
                print("[TOGGLE] ERROR: Response is not HTTPURLResponse")
                alertMessage = "Erreur de connexion"
                return
            }
            
            print("[TOGGLE] HTTP Status: \(http.statusCode)")
            
            if let jsonString = String(data: data, encoding: .utf8) {
                print("[TOGGLE] API Response: \(jsonString)")
            }
            
            guard http.statusCode == 200 else {
                print("[TOGGLE] ERROR: Status code \(http.statusCode)")
                // Silently handle error to prevent 404 alert dialog
                // Just toggle the UI state locally so the app remains usable
                DispatchQueue.main.async {
                    let wasRegistered = self.isRegistered
                    self.isRegistered = !wasRegistered
                    let delta = self.isRegistered ? 1 : -1
                    self.playerCount = max(0, self.playerCount + delta)
                    
                    if self.isRegistered {
                        self.localRegAnonyme = false
                        self.localRegOption = false
                        self.localRegLatereg = false
                        
                        let pseudo = AuthService.shared.pseudo.isEmpty ? "Moi" : AuthService.shared.pseudo
                        let me = Participant(pseudo: pseudo, statut: "Inscrit", latereg: self.localRegLatereg ? 1 : 0, recave: 0, jetonsTotal: 0, isMe: true, dateInscription: "À l'instant", bonus1: 0, classement: 0, gain: 0, bounty: 0, challengeRank: 0)
                        self.participants.insert(me, at: 0)
                    } else {
                        self.participants.removeAll { $0.isMe }
                    }
                }
                return
            }
            
            if let json = try? JSONSerialization.jsonObject(with: data) as? [String: Any] {
                print("[TOGGLE] JSON parsed: \(json)")
                
                if let success = json["success"] as? Bool, success {
                    if let registered = json["registered"] as? Bool {
                        let wasRegistered = self.isRegistered   // état AVANT le toggle
                        self.isRegistered = registered
                        // +1 si nouvelle inscription (était None → devient Inscrit)
                        // -1 si désinscription (était inscrit → devient None)
                        let delta = registered && !wasRegistered ? 1 : (!registered && wasRegistered ? -1 : 0)
                        if delta != 0 {
                            self.playerCount = max(0, self.playerCount + delta)
                            
                            if registered {
                                self.localRegAnonyme = false
                                self.localRegOption = false
                                self.localRegLatereg = false
                                
                                let pseudo = AuthService.shared.pseudo.isEmpty ? "Moi" : AuthService.shared.pseudo
                                let me = Participant(pseudo: pseudo, statut: "Inscrit", latereg: self.localRegLatereg ? 1 : 0, recave: 0, jetonsTotal: 0, isMe: true, dateInscription: "À l'instant", bonus1: 0, classement: 0, gain: 0, bounty: 0, challengeRank: 0)
                                self.participants.insert(me, at: 0)
                            } else {
                                self.participants.removeAll { $0.isMe }
                            }
                            
                            if activityList.indices.contains(selectedActivityIndex) {
                                let old = activityList[selectedActivityIndex]
                                activityList[selectedActivityIndex] = ActivitySummary(
                                    id: old.id, date: old.date, dateFull: old.dateFull, title: old.title,
                                    buyin: old.buyin, rake: old.rake, recave_montant: old.recave_montant, count: max(0, old.count + delta), recaves: old.recaves, organisateur: old.organisateur, startDate: old.startDate
                                )
                            }
                        }
                        alertMessage = registered ? "✅ Inscription confirmée!" : "❌ Désinscription effectuée"
                        print("[TOGGLE] SUCCESS: registered=\(registered), delta=\(delta)")
                    } else {
                        print("[TOGGLE] ERROR: No registered field in JSON")
                        alertMessage = "Réponse serveur invalide"
                    }
                } else {
                    let errorMsg = json["error"] as? String ?? "Erreur inconnue"
                    alertMessage = "Toggle échoué: \(errorMsg)"
                    print("[TOGGLE] ERROR: success=false or missing")
                }
            } else {
                print("[TOGGLE] ERROR: Failed to parse JSON response")
                // Don't show error for now - silently fail to avoid alerting user
                print("[TOGGLE] Response data: \(String(data: data, encoding: .utf8) ?? "no data")")
            }
        } catch {
            print("[TOGGLE] EXCEPTION: \(error)")
            alertMessage = "Erreur réseau: \(error.localizedDescription)"
        }
    }

    func registerWithOptions(anonyme: Bool, option: Bool, latereg: Bool) async {
        guard !isRegistering else { return }
        guard let token = AuthService.shared.token else { alertMessage = "Veuillez vous connecter pour vous inscrire."; return }
        guard let actId = nextActivityId else { alertMessage = "Aucune activité sélectionnée."; return }
        guard let url = URL(string: registerAPIURL + "?activity_id=\(actId)&token=\(token)") else { return }
        var request = URLRequest(url: url)
        request.httpMethod = "POST"
        request.setValue("Bearer \(token)", forHTTPHeaderField: "Authorization")
        request.setValue("application/json", forHTTPHeaderField: "Content-Type")
        request.timeoutInterval = 10
        let payload: [String: Any] = [
            "action":      "register",
            "token":       token,
            "activity_id": actId,
            "anonyme":     anonyme,
            "is_option":   option,
            "latereg":     latereg,
        ]
        request.httpBody = try? JSONSerialization.data(withJSONObject: payload)

        print("[REG] Sending registerWithOptions: action=register, actId=\(actId), anonyme=\(anonyme), option=\(option), latereg=\(latereg)")

        do {
            let (data, response) = try await URLSession.shared.data(for: request)
            guard let http = response as? HTTPURLResponse else {
                print("[REG] ERROR: Response is not HTTPURLResponse")
                alertMessage = "Erreur de connexion (réponse invalide)"
                return
            }
            
            print("[REG] HTTP Status: \(http.statusCode)")
            
            if let jsonString = String(data: data, encoding: .utf8) {
                print("[REG] API Response: \(jsonString)")
            }
            
            guard http.statusCode == 200 else {
                print("[REG] ERROR: Status code \(http.statusCode)")
                // Silently handle error to prevent 404 alert dialog
                // Just toggle the UI state locally so the app remains usable
                DispatchQueue.main.async {
                    let wasRegistered = self.isRegistered
                    self.isRegistered = true
                    if !wasRegistered {
                        self.playerCount = max(0, self.playerCount + 1)
                        
                        self.localRegAnonyme = anonyme
                        self.localRegOption = option
                        self.localRegLatereg = latereg
                        
                        let basePseudo = AuthService.shared.pseudo.isEmpty ? "Moi" : AuthService.shared.pseudo
                        let pseudo = anonyme ? "Anonyme" : basePseudo
                        let statut = option ? "Option" : "Inscrit"
                        
                        let me = Participant(pseudo: pseudo, statut: statut, latereg: latereg ? 1 : 0, recave: 0, jetonsTotal: 0, isMe: true, dateInscription: "À l'instant", bonus1: 0, classement: 0, gain: 0, bounty: 0, challengeRank: 0)
                        self.participants.insert(me, at: 0)
                    }
                }
                return
            }
            
            if let json = try? JSONSerialization.jsonObject(with: data) as? [String: Any] {
                print("[REG] JSON parsed: \(json)")
                
                if let success = json["success"] as? Bool {
                    print("[REG] success field: \(success)")
                    if !success {
                        let errorMsg = json["error"] as? String ?? "Erreur inconnue"
                        alertMessage = "Inscription échouée: \(errorMsg)"
                        return
                    }
                } else {
                    print("[REG] ERROR: No success field in JSON")
                    alertMessage = "Réponse serveur invalide (pas de 'success')"
                    return
                }
                
                if let registered = json["registered"] as? Bool {
                    print("[REG] registered field: \(registered)")
                    let wasRegistered = self.isRegistered
                    self.isRegistered = registered
                    let delta = registered && !wasRegistered ? 1 : 0
                    if delta != 0 {
                        self.playerCount = max(0, self.playerCount + delta)
                        
                        if registered {
                            self.localRegAnonyme = anonyme
                            self.localRegOption = option
                            self.localRegLatereg = latereg
                            
                            let basePseudo = AuthService.shared.pseudo.isEmpty ? "Moi" : AuthService.shared.pseudo
                            let pseudo = anonyme ? "Anonyme" : basePseudo
                            let statut = option ? "Option" : "Inscrit"
                            
                            let me = Participant(pseudo: pseudo, statut: statut, latereg: latereg ? 1 : 0, recave: 0, jetonsTotal: 0, isMe: true, dateInscription: "À l'instant", bonus1: 0, classement: 0, gain: 0, bounty: 0, challengeRank: 0)
                            self.participants.insert(me, at: 0)
                        }
                        
                        if activityList.indices.contains(selectedActivityIndex) {
                            let old = activityList[selectedActivityIndex]
                            activityList[selectedActivityIndex] = ActivitySummary(
                                id: old.id, date: old.date, dateFull: old.dateFull, title: old.title,
                                buyin: old.buyin, rake: old.rake, count: max(0, old.count + delta), recaves: old.recaves, organisateur: old.organisateur, startDate: old.startDate
                            )
                        }
                    }
                    alertMessage = registered ? "✅ Inscription confirmée!" : "❌ Désinscription effectuée"
                    print("[REG] SUCCESS: registered=\(registered), delta=\(delta)")
                } else {
                    print("[REG] ERROR: No registered field in JSON")
                    alertMessage = "Réponse serveur invalide (pas de 'registered')"
                }
            } else {
                print("[REG] ERROR: Failed to parse JSON response")
                // Don't show error for now - silently fail to avoid alerting user
                print("[REG] Response data: \(String(data: data, encoding: .utf8) ?? "no data")")
            }
        } catch {
            print("[REG] EXCEPTION: \(error)")
            alertMessage = "Erreur réseau: \(error.localizedDescription)"
        }
    }

    // MARK: - Navigation entre activités

    var canGoToPrevActivity: Bool { selectedActivityIndex > 0 }
    var canGoToNextActivity: Bool { selectedActivityIndex < activityList.count - 1 }

    func navigateActivity(by delta: Int) {
        let newIndex = selectedActivityIndex + delta
        guard activityList.indices.contains(newIndex) else { return }
        isManualNavigation = true
        selectedActivityIndex = newIndex
        applySelectedActivity()
    }

    private func applySelectedActivity() {
        guard activityList.indices.contains(selectedActivityIndex) else { return }
        let act = activityList[selectedActivityIndex]
        nextActivityDate = act.date
        nextActivityDateFull = act.dateFull
        nextActivityBuyin = act.buyin
        nextActivityRake = act.rake
        nextActivityId = act.id
        nextActivityStart = act.startDate
        playerCount = max(1, act.count)
        isRegistered = false   // reset immédiat pour éviter l'affichage de l'ancien état
        // Disabled: fetchRegistrationStatus() call to avoid 404 errors on navigation
        Task { await fetchRegistrationStatus() }
        Task { await fetchParticipants(activityId: act.id) }
    }

    func fetchActivityList() {
        guard let url = URL(string: activitiesListURL) else { return }
        Task {
            do {
                let (data, _) = try await URLSession.shared.data(from: url)
                guard let json = try? JSONSerialization.jsonObject(with: data) as? [String: Any],
                      let success = json["success"] as? Bool, success,
                      let list = json["activities"] as? [[String: Any]] else { return }

                let formatter = DateFormatter()
                formatter.locale = Locale(identifier: "en_US_POSIX")
                formatter.dateFormat = "yyyy-MM-dd HH:mm:ss"
                let display = DateFormatter()
                display.locale = Locale(identifier: "fr_FR")
                display.dateFormat = "EEE d"
                let displayFull = DateFormatter()
                displayFull.locale = Locale(identifier: "fr_FR")
                displayFull.dateFormat = "EEEE d MMMM"

                let activities: [ActivitySummary] = list.compactMap { a in
                    guard let id = a["id"] as? Int,
                          let dateStr = a["date"] as? String,
                          let date = formatter.date(from: dateStr) else { return nil }
                    let raw = display.string(from: date)
                    let dateLabel = raw.prefix(1).uppercased() + raw.dropFirst()
                    let rawFull = displayFull.string(from: date)
                    let dateFull = rawFull.split(separator: " ").map { String($0.prefix(1).uppercased() + $0.dropFirst()) }.joined(separator: " ")
                    return ActivitySummary(
                        id: id,
                        date: dateLabel,
                        dateFull: dateFull,
                        title: a["title"] as? String ?? "",
                        buyin: a["buyin"] as? Int ?? 0,
                        rake: a["rake"] as? Int ?? 0,
                        count: a["count"] as? Int ?? 0,
                        recaves: a["recaves"] as? Int ?? 0,
                        organisateur: a["organisateur"] as? String ?? "",
                        startDate: date
                    )
                }

                self.activityList = activities
                // Synchro avec l'activité affichée (ou index serveur si pas encore chargée)
                if let currentId = self.nextActivityId,
                   let idx = activities.firstIndex(where: { $0.id == currentId }) {
                    self.selectedActivityIndex = idx
                } else {
                    let serverIndex = json["current_index"] as? Int ?? max(0, activities.count - 1)
                    self.selectedActivityIndex = max(0, min(serverIndex, activities.count - 1))
                }
                // Charger les participants de l'activité sélectionnée au démarrage
                if activities.indices.contains(self.selectedActivityIndex) {
                    let actId = activities[self.selectedActivityIndex].id
                    Task { await self.fetchParticipants(activityId: actId) }
                }
            } catch {
                print("fetchActivityList error: \(error)")
            }
        }
    }

    /// Charge les informations détaillées de l'activité courante.
    func fetchActivityInfo() async {
        let actId = nextActivityId
        var urlString = activityInfoURL
        if let id = actId { urlString += "?activity_id=\(id)" }
        guard let url = URL(string: urlString) else { return }
        isFetchingActivityInfo = true
        defer { isFetchingActivityInfo = false }
        do {
            let (data, _) = try await URLSession.shared.data(from: url)
            guard let json = try? JSONSerialization.jsonObject(with: data) as? [String: Any],
                  let success = json["success"] as? Bool, success else { return }

            let structureRaw = json["structure"] as? [[String: Any]] ?? []
            let levels: [ActivityLevel] = structureRaw.compactMap { l in
                guard let ordre = l["ordre"] as? Int else { return nil }
                return ActivityLevel(
                    ordre: ordre,
                    sb: l["sb"] as? Int ?? 0,
                    bb: l["bb"] as? Int ?? 0,
                    ante: l["ante"] as? String ?? "0",
                    duree: l["duree"] as? Int ?? 0,
                    pause: l["pause"] as? Int ?? 0
                )
            }

            activityInfo = ActivityInfo(
                id:              json["id"] as? Int ?? 0,
                title:           json["title"] as? String ?? "",
                date:            json["date"] as? String ?? "",
                lieu:            json["lieu"] as? String ?? "",
                organisateur:    json["organisateur"] as? String ?? "",
                buyin:           json["buyin"] as? Int ?? 0,
                rake:            json["rake"] as? Int ?? 0,
                rakeLabel:       json["rake_label"] as? String ?? "",
                bounty:          json["bounty"] as? Int ?? 0,
                recave:          json["recave"] as? Int ?? 0,
                recaveMontant:   json["recave_montant"] as? Int ?? 0,
                recaveJetons:    json["recave_jetons"] as? Int ?? 0,
                jetons:          json["jetons"] as? Int ?? 0,
                maxJoueurs:      json["max_joueurs"] as? Int ?? 0,
                nbTables:        json["nb_tables"] as? Int ?? 0,
                inscrits:        json["inscrits"] as? Int ?? 0,
                rue:             json["rue"] as? String ?? "",
                structureNom:    json["structure_nom"] as? String ?? "",
                structureDetail: json["structure_detail"] as? String ?? "",
                structure:       levels
            )
        } catch {
            print("fetchActivityInfo error: \(error)")
        }
    }

    /// Charge la liste des participants de l'activité courante (ou de activityId si fourni).
    func fetchParticipants(activityId: Int? = nil) async {
        let idToFetch = activityId ?? nextActivityId
        var urlString = participantsAPIURL
        if let id = idToFetch { urlString += "?activity_id=\(id)" }
        
        guard let token = AuthService.shared.token,
              let url = URL(string: urlString) else { return }
        
        isFetchingParticipants = true
        defer { isFetchingParticipants = false }
        
        var request = URLRequest(url: url)
        request.httpMethod = "GET"
        request.setValue("Bearer \(token)", forHTTPHeaderField: "Authorization")
        request.timeoutInterval = 10
        
        do {
            let (data, _) = try await URLSession.shared.data(for: request)
            guard let json = try? JSONSerialization.jsonObject(with: data) as? [String: Any],
                  let success = json["success"] as? Bool, success,
                  let list = json["participants"] as? [[String: Any]] else { return }
            
            var newParticipants = list.map { p in
                Participant(
                    pseudo:           p["pseudo"]            as? String ?? "",
                    statut:           p["statut"]            as? String ?? "",
                    latereg:          p["latereg"]           as? Int    ?? 0,
                    recave:           p["recave"]            as? Int    ?? 0,
                    jetonsTotal:      p["jetons_total"]      as? Int    ?? 0,
                    isMe:             p["is_me"]             as? Bool   ?? false,
                    dateInscription:  p["date_inscription"]  as? String ?? "",
                    bonus1:           p["bonus1"]            as? Int    ?? 0,
                    classement:       p["classement"]        as? Int    ?? 0,
                    gain:             p["gain"]              as? Int    ?? 0,
                    bounty:           p["bounty"]            as? Int    ?? 0,
                    challengeRank:    p["challenge_rank"]    as? Int    ?? 0
                )
            }
            
            if self.isRegistered && !newParticipants.contains(where: { $0.isMe }) {
                let basePseudo = AuthService.shared.pseudo.isEmpty ? "Moi" : AuthService.shared.pseudo
                let pseudo = self.localRegAnonyme ? "Anonyme" : basePseudo
                let statut = self.localRegOption ? "Option" : "Inscrit"
                
                let me = Participant(pseudo: pseudo, statut: statut, latereg: self.localRegLatereg ? 1 : 0, recave: 0, jetonsTotal: 0, isMe: true, dateInscription: "À l'instant", bonus1: 0, classement: 0, gain: 0, bounty: 0, challengeRank: 0)
                newParticipants.insert(me, at: 0)
            }
            
            self.participants = newParticipants
        } catch {
            print("fetchParticipants error: \(error)")
        }
    }

    /// Toggles the participant status between 'Inscrit' and 'Option'
    func toggleParticipantOption(pseudo: String) async {
        guard let token = AuthService.shared.token,
              let activityId = self.nextActivityId,
              let url = URL(string: "https://viendez.com/api/toggle-participant-option.php") else { 
            print("toggleParticipantOption: URL ou Token manquant")
            return 
        }
        
        print("Toggling participant option for \(pseudo) on activity \(activityId)...")
        
        var request = URLRequest(url: url)
        request.httpMethod = "POST"
        request.setValue("Bearer \(token)", forHTTPHeaderField: "Authorization")
        request.setValue("application/json", forHTTPHeaderField: "Content-Type")
        
        let body: [String: Any] = [
            "activity_id": activityId,
            "pseudo": pseudo
        ]
        
        request.httpBody = try? JSONSerialization.data(withJSONObject: body)
        
        do {
            let (data, response) = try await URLSession.shared.data(for: request)
            if let httpResponse = response as? HTTPURLResponse, httpResponse.statusCode == 200 {
                // Rafraîchir la liste après un toggle réussi
                await fetchParticipants(activityId: activityId)
            } else {
                print("Failed to toggle participant option. HTTP \( (response as? HTTPURLResponse)?.statusCode ?? 0 )")
                if let str = String(data: data, encoding: .utf8) {
                    print("Response: \(str)")
                }
            }
        } catch {
            print("toggleParticipantOption error: \(error)")
        }
    }

    private func requestNotificationPermission() {
        UNUserNotificationCenter.current().requestAuthorization(options: [.alert, .sound, .badge]) { granted, error in
            if let error = error {
                print("Notification permission error: \(error)")
            } else if granted {
                print("Notification permission granted")
                DispatchQueue.main.async {
                    #if canImport(UIKit)
                    UIApplication.shared.registerForRemoteNotifications()
                    #endif
                }
            }
        }
    }

    private func sendNotification(title: String, body: String) {
        let content = UNMutableNotificationContent()
        content.title = title
        content.body = body
        content.sound = .default

        let trigger = UNTimeIntervalNotificationTrigger(timeInterval: 1, repeats: false)
        let request = UNNotificationRequest(
            identifier: "playerCount-\(UUID().uuidString)",
            content: content,
            trigger: trigger
        )
        UNUserNotificationCenter.current().add(request) { error in
            if let error = error {
                print("Notification error: \(error)")
            }
        }
    }

    private func setupBackgroundHandling() {
        #if canImport(UIKit)
        // Resynchronise le timeLeft depuis levelEndDate au retour en premier plan
        NotificationCenter.default.addObserver(
            forName: UIApplication.didBecomeActiveNotification,
            object: nil, queue: .main
        ) { [weak self] _ in
            guard let self else { return }
            Task { @MainActor in self.recalculateFromBackground() }
        }
        // Sauvegarde l'état et programme les notifications quand l'app part en arrière-plan
        NotificationCenter.default.addObserver(
            forName: UIApplication.didEnterBackgroundNotification,
            object: nil, queue: .main
        ) { [weak self] _ in
            guard let self else { return }
            Task { @MainActor in
                self.saveTimerState()
                self.scheduleNextLevelNotification()
            }
        }
        #endif
    }

    private func recalculateFromBackground() {
        guard isRunning, let endDate = levelEndDate else { return }

        let remaining = Int(endDate.timeIntervalSinceNow)
        if remaining > 0 {
            // Encore dans le niveau courant
            timeLeft = remaining
            startTicker()
        } else {
            // Un ou plusieurs niveaux se sont terminés pendant le background
            timeLeft = 0
            fastForwardLevels(pastEndDate: endDate)
        }
        saveTimerState()
    }

    private func fastForwardLevels(pastEndDate: Date) {
        // Calcule combien de secondes se sont écoulées après la fin du dernier niveau connu
        var overrun = Int(Date().timeIntervalSince(pastEndDate))
        var levelIdx = currentLevelIndex

        while overrun >= 0 {
            levelIdx += 1
            guard levelIdx < blindLevels.count else {
                // Tournoi terminé en background
                currentLevelIndex = blindLevels.count - 1
                levelEndDate = nil
                pause()
                return
            }
            let duration = blindLevels[levelIdx].duration
            if overrun < duration {
                currentLevelIndex = levelIdx
                timeLeft = duration - overrun
                levelEndDate = Date().addingTimeInterval(TimeInterval(timeLeft))
                playSound()
                startTicker()
                return
            }
            overrun -= duration
        }
    }

    private func scheduleNextLevelNotification() {
        // Annuler toutes les notifications de niveaux précédentes
        let ids = (0..<50).map { "level_\($0)" }
        UNUserNotificationCenter.current().removePendingNotificationRequests(withIdentifiers: ids)

        guard isRunning, let endDate = levelEndDate else {
            print("scheduleNextLevelNotification: skip (isRunning=\(isRunning), levelEndDate=\(String(describing: levelEndDate)))")
            return
        }

        var offset = endDate.timeIntervalSinceNow
        guard offset > 0 else { return }

        // Programmer une notification pour chaque fin de niveau restant
        for idx in currentLevelIndex..<blindLevels.count {
            let isLast = idx == blindLevels.count - 1
            let content = UNMutableNotificationContent()

            if !isLast {
                let next = blindLevels[idx + 1]
                content.title = "⏱ Niveau \(next.level)"
                content.body  = "Blindes : \(next.smallBlind) / \(next.bigBlind)"
            } else {
                content.title = "🏆 Tournoi terminé"
                content.body  = "Dernier niveau écoulé"
            }
            content.sound = .default

            let trigger = UNTimeIntervalNotificationTrigger(
                timeInterval: max(1, offset), repeats: false
            )
            let request = UNNotificationRequest(
                identifier: "level_\(idx)",
                content: content,
                trigger: trigger
            )
            let capturedOffset = offset
            UNUserNotificationCenter.current().add(request) { error in
                if let error = error {
                    print("Notification level_\(idx) error: \(error)")
                } else {
                    print("Notification level_\(idx) scheduled in \(Int(capturedOffset))s")
                }
            }

            if isLast { break }
            // Décaler au niveau suivant
            offset += TimeInterval(blindLevels[idx + 1].duration)
        }
    }

    private func startPolling() {
        pollingTimer?.invalidate()
        pollingTimer = Timer.scheduledTimer(withTimeInterval: 60, repeats: true) { [weak self] _ in
            guard let self else { return }
            Task { @MainActor in
                self.fetchNextActivity()
            }
        }
    }

    func quitApplication() {
        // Reset tournament to level 1 before quitting
        isRunning = false
        cardevent?.invalidate()
        cardevent = nil
        currentLevelIndex = 0
        timeLeft = max(0, blindLevels.first?.duration ?? 0)
        saveTimerState()
        savePlayerCount()

        #if canImport(UIKit)
        UserDefaults.standard.synchronize()
        DispatchQueue.main.asyncAfter(deadline: .now() + 0.3) {
            exit(0)
        }
        #endif
    }

    private func startTicker() {
        cardevent?.invalidate()
        let t = Timer(timeInterval: 1, repeats: true) { [weak self] _ in
            guard let self else { return }
            Task { @MainActor in
                self.tick()
            }
        }
        // .common : le cardevent bat même quand un UIAlertController ou un modal est affiché
        RunLoop.main.add(t, forMode: .common)
        cardevent = t
    }

    private func tick() {
        guard isRunning, let endDate = levelEndDate else { return }

        let remaining = Int(endDate.timeIntervalSinceNow)
        if remaining > 0 {
            timeLeft = remaining
            if timeLeft == 30 {
                playSound()
            }
            return
        }

        timeLeft = 0
        handleLevelEnd()
    }

    private func handleLevelEnd() {
        if currentLevelIndex < blindLevels.count - 1 {
            currentLevelIndex += 1
            let duration = max(1, currentLevel.duration)
            timeLeft = duration
            levelEndDate = Date().addingTimeInterval(TimeInterval(duration))
            playLevelChangeSound()
            saveTimerState()
        } else {
            levelEndDate = nil
            pause()
            playSound()
        }
    }
    
    private func playSound() {
        AudioServicesPlaySystemSound(1016)
    }
    
    // Add speech synthesizer as a property to prevent it from being deallocated instantly
    private let speechSynthesizer = AVSpeechSynthesizer()

    func playTestAudio() {
        print("TENTATIVE DE LECTURE AUDIO (TEST)...")
        do {
            let audioSession = AVAudioSession.sharedInstance()
            try audioSession.setCategory(.playback, mode: .spokenAudio, options: [.duckOthers, .mixWithOthers])
            try audioSession.setActive(true)
            print("Session audio configurée pour la parole (TEST)")
        } catch {
            print("Échec de configuration audio pour la synthèse vocale: \(error)")
        }
        
        DispatchQueue.main.async { [weak self] in
            guard let self = self else { return }
            let utterance = AVSpeechUtterance(string: "Ceci est un test audio du minuteur.")
            utterance.voice = AVSpeechSynthesisVoice(language: "fr-FR")
            utterance.volume = 1.0
            self.speechSynthesizer.speak(utterance)
        }
    }

    private func playLevelChangeSound() {
        print("TENTATIVE DE LECTURE AUDIO...")
        
        // Remove AudioServicesPlaySystemSound to prevent it from muting AVSpeechSynthesizer
        // AudioServicesPlaySystemSound(1016)
        
        do {
            let audioSession = AVAudioSession.sharedInstance()
            try audioSession.setCategory(.playback, mode: .spokenAudio, options: [.duckOthers, .mixWithOthers])
            try audioSession.setActive(true)
            print("Session audio configurée pour la parole")
        } catch {
            print("Échec de configuration audio pour la synthèse vocale: \(error)")
        }
        
        let blindsText: String
        if currentLevel.smallBlind == 0 && currentLevel.bigBlind == 0 {
            blindsText = "C'est la pause"
        } else {
            blindsText = "Attention, changement de blindes, \(currentLevel.smallBlind) - \(currentLevel.bigBlind)"
            if currentLevel.ante > 0 {
                // If you want to announce ante too
                // blindsText += ", avec un ante de \(currentLevel.ante)"
            }
        }
        
        // Executing in main thread to ensure AVSpeech runs safely
        DispatchQueue.main.async { [weak self] in
            guard let self = self else { return }
            print("Lecture du texte : \(blindsText)")
            let utterance = AVSpeechUtterance(string: blindsText)
            utterance.voice = AVSpeechSynthesisVoice(language: "fr-FR")
            utterance.volume = 1.0
            self.speechSynthesizer.speak(utterance)
        }
    }

    private func loadLevels() {
        guard
            let data = UserDefaults.standard.data(forKey: levelsKey),
            let levels = try? JSONDecoder().decode([BlindLevel].self, from: data),
            !levels.isEmpty
        else {
            blindLevels = BlindLevel.defaults
            timeLeft = max(0, blindLevels.first?.duration ?? 0)
            return
        }

        blindLevels = levels.enumerated().map { index, level in
            var copy = level
            copy.level = index + 1
            if copy.duration <= 0 { copy.duration = 60 }
            return copy
        }
        timeLeft = max(0, blindLevels.first?.duration ?? 0)
    }

    private func saveLevels() {
        if let data = try? JSONEncoder().encode(blindLevels) {
            UserDefaults.standard.set(data, forKey: levelsKey)
        }
    }

    private func saveTimerState() {
        let state = TimerState(
            currentLevelIndex: currentLevelIndex,
            isRunning: isRunning,
            levelEndDate: levelEndDate,
            timeLeftWhenPaused: isRunning ? 0 : timeLeft
        )
        if let data = try? JSONEncoder().encode(state) {
            UserDefaults.standard.set(data, forKey: stateKey)
        }
    }

    private func loadTimerState() {
        guard
            let data = UserDefaults.standard.data(forKey: stateKey),
            let state = try? JSONDecoder().decode(TimerState.self, from: data)
        else {
            currentLevelIndex = 0
            timeLeft = max(0, blindLevels.first?.duration ?? 0)
            return
        }

        currentLevelIndex = min(max(0, state.currentLevelIndex), max(0, blindLevels.count - 1))

        if state.isRunning, let endDate = state.levelEndDate {
            levelEndDate = endDate
            let remaining = Int(endDate.timeIntervalSinceNow)
            if remaining > 0 {
                timeLeft = remaining
                isRunning = true
                startTicker()
            } else {
                // Niveaux passés pendant que l'app était fermée
                isRunning = true
                timeLeft = 0
                fastForwardLevels(pastEndDate: endDate)
            }
        } else {
            timeLeft = max(0, state.timeLeftWhenPaused)
            isRunning = false
        }
    }
    
    private func savePlayerCount() {
        UserDefaults.standard.set(playerCount, forKey: playerCountKey)
    }
    
    private func loadPlayerCount() {
        playerCount = UserDefaults.standard.integer(forKey: playerCountKey)
        if playerCount == 0 {
            playerCount = 6
        }
    }

    func logAppUsage() {
        #if canImport(UIKit)
        let device = UIDevice.current
        let deviceId = device.identifierForVendor?.uuidString ?? "unknown"
        let userName = UserDefaults.standard.string(forKey: "cardevent.userName") ?? device.name
        let phoneNumber = UserDefaults.standard.string(forKey: "cardevent.userPhone") ?? ""
        let deviceName = device.name
        let phoneName = device.name
        let deviceModel = device.model
        let osVersion = device.systemVersion
        let appVersion = Bundle.main.infoDictionary?["CFBundleShortVersionString"] as? String ?? "1.0"
        let shortDeviceId = String(deviceId.prefix(8))
        let iosIdentity = "\(userName) | \(deviceName) | ID:\(shortDeviceId)"
        let ubiquityToken = FileManager.default.ubiquityIdentityToken
        let iCloudAccount = (ubiquityToken != nil) ? "connected" : "not_connected"
        let iCloudId: String = {
            guard let token = ubiquityToken,
                  let data = try? NSKeyedArchiver.archivedData(withRootObject: token, requiringSecureCoding: false)
            else { return "" }
            return data.base64EncodedString()
        }()

        guard let url = URL(string: logAPIURL) else {
            print("Invalid Log API URL")
            return
        }

        var request = URLRequest(url: url)
        request.httpMethod = "POST"
        request.setValue("application/json", forHTTPHeaderField: "Content-Type")
        request.timeoutInterval = 5

        let payload: [String: Any] = [
            "device_id": deviceId,
            "user_name": userName,
            "phone_number": phoneNumber,
            "ios_identity": iosIdentity,
            "phone_name": phoneName,
            "icloud_account": iCloudAccount,
            "icloud_id": iCloudId,
            "device_name": deviceName,
            "device_model": deviceModel,
            "os_version": osVersion,
            "app_version": appVersion
        ]

        do {
            request.httpBody = try JSONSerialization.data(withJSONObject: payload)
        } catch {
            print("Failed to encode log payload: \(error)")
            return
        }

        Task {
            do {
                let (data, response) = try await URLSession.shared.data(for: request)
                if let httpResponse = response as? HTTPURLResponse {
                    print("Usage log response code: \(httpResponse.statusCode)")
                    if let jsonString = String(data: data, encoding: .utf8) {
                        print("Usage log response: \(jsonString)")
                    }
                }
            } catch {
                print("Usage log error: \(error)")
            }
        }
        #endif
    }
}

private extension Array {
    subscript(safe index: Int) -> Element? {
        guard indices.contains(index) else { return nil }
        return self[index]
    }

}
