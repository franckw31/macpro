import SwiftUI

// MARK: - MiniLiveTimerVM

@MainActor
private final class MiniLiveTimerVM: ObservableObject {
    @Published var secondsRemaining: Int = 0
    @Published var durationSeconds:   Int = 1
    @Published var levelName:  String = ""
    @Published var blindsText: String = "--/--"
    @Published var isPaused:   Bool   = false
    @Published var status:     String = "loading"

    private var tickTimer: Timer?
    private var syncTimer: Timer?
    private(set) var activityId: Int = 0

    func start(activityId: Int) {
        guard activityId > 0 else { status = "idle"; return }
        self.activityId = activityId
        Task { await sync() }
        syncTimer = Timer.scheduledTimer(withTimeInterval: 5, repeats: true) { [weak self] _ in
            Task { await self?.sync() }
        }
        tickTimer = Timer.scheduledTimer(withTimeInterval: 1, repeats: true) { [weak self] _ in
            guard let self else { return }
            Task { @MainActor in
                if !self.isPaused && self.secondsRemaining > 0 { self.secondsRemaining -= 1 }
            }
        }
    }

    func stop() {
        tickTimer?.invalidate(); syncTimer?.invalidate()
        tickTimer = nil; syncTimer = nil
    }

    private func sync() async {
        guard activityId > 0,
              let url = URL(string: "https://viendez.com/panel/timer-api.php?uid=\(activityId)") else { return }
        do {
            let (data, _) = try await URLSession.shared.data(from: url)
            struct Resp: Decodable {
                let status: String
                let seconds_remaining: Int?
                let duration_seconds: Int?
                let blinds_text: String?
                let level_name: String?
                let is_paused: Bool?
            }
            let r = try JSONDecoder().decode(Resp.self, from: data)
            self.status = r.status
            // Only adjust local per-second counter if server value drifts significantly
            if let serverSeconds = r.seconds_remaining {
                let local = self.secondsRemaining
                if abs(serverSeconds - local) > 2 {
                    self.secondsRemaining = serverSeconds
                }
            }
            self.durationSeconds  = max(1, r.duration_seconds ?? self.durationSeconds)
            self.blindsText       = r.blinds_text  ?? self.blindsText
            self.levelName        = r.level_name   ?? self.levelName
            self.isPaused         = r.is_paused    ?? false
        } catch {}
    }

    var timeString: String {
        let s = max(0, secondsRemaining)
        return String(format: "%d:%02d", s / 60, s % 60)
    }

    // Display time rounded down to the nearest 10 seconds (e.g. 8:30, 8:20, 8:10)
    var timeTensString: String {
        let s = max(0, secondsRemaining)
        let tens = (s / 10) * 10
        return String(format: "%d:%02d", tens / 60, tens % 60)
    }

    var progress: Double {
        guard durationSeconds > 0 else { return 0 }
        return Double(secondsRemaining) / Double(durationSeconds)
    }
}

// MARK: - HomeView

struct HomeView: View {
    @ObservedObject var viewModel: PokerTimerViewModel
    @ObservedObject private var auth = AuthService.shared
    let showPrizepool: Bool

    @State private var showActivityInfo      = false
    @State private var showParticipants      = false
    @State private var showPlayerProfile     = false
    @State private var showLiveTimer         = false
    @State private var photoUrl:   String    = ""
    @State private var showOptions           = false
    @StateObject private var miniTimer = MiniLiveTimerVM()
    @State private var regAnonymous          = false
    @State private var regOption             = false
    @State private var regLatereg            = false
    @State private var showHelp              = false

    @State private var prizepoolInput: String = ""
    @State private var buyinsInput: String = ""
    @State private var payExtraPlayer: Bool = false
    @State private var repartition: [(place: Int, gain: Int)] = []
    @State private var showResult: Bool = false

    private let cyan   = Color(red: 0, green: 0.82, blue: 1)
    private let gold   = Color(red: 1, green: 0.84, blue: 0)
    private let green  = Color(red: 0.18, green: 0.85, blue: 0.46)
    private let purple = Color(red: 0.55, green: 0.35, blue: 1)

    // Activité courante
    private var currentActivity: ActivitySummary? {
        guard viewModel.activityList.indices.contains(viewModel.selectedActivityIndex) else { return nil }
        return viewModel.activityList[viewModel.selectedActivityIndex]
    }

    private var hasResults: Bool {
        viewModel.participants.contains { $0.gain != 0 }
    }

    // Paid participants sorted by ranking (classement)
    private var paidParticipants: [Participant] {
        viewModel.participants.filter { $0.classement > 0 && $0.gain != 0 }.sorted { $0.classement < $1.classement }
    }

    var body: some View {
        NavigationStack {
            ScrollViewReader { proxy in
                ScrollView {
                VStack(spacing: 20) {
                    headerSection
                    activityCard

                    if showOptions {
                        optionsCard
                    } else {
                        if showPrizepool {
                            prizepoolCalculator
                                .id("prizepool")
                                .transition(.move(edge: .bottom).combined(with: .opacity))
                        } else {
                            shortcutsGrid
                        }
                    }

                    if hasResults { podiumCard } else { registrationCard }

                    Spacer(minLength: 20)
                }
                .padding(.horizontal, 16)
                .padding(.top, 8)
                }
                // plus de NotificationCenter, tout est piloté par le paramètre showPrizepool
            }
            .background {
                Image("LaunchBackground")
                    .resizable()
                    .scaledToFill()
                    .blur(radius: 2)
                    .ignoresSafeArea()
                // stronger dark overlay to improve contrast/readability over the background image
                Color.black.opacity(0.72).ignoresSafeArea()
            }
            .navigationTitle("")
            .navigationBarHidden(true)
            .task { 
                await loadPhoto() 
                await viewModel.fetchRegistrationStatus()
            }
            
            .sheet(isPresented: $showLiveTimer) {
                if let act = currentActivity { NavigationStack { LiveView(activityId: act.id, activityTitle: act.title) } }
            }
            .sheet(isPresented: $showActivityInfo) { ActivityInfoView(viewModel: viewModel) }
            .sheet(isPresented: $showParticipants) { ParticipantsListView(viewModel: viewModel) }
            .sheet(isPresented: $showPlayerProfile) {
                PlayerProfileView(pseudo: auth.pseudo, participant: viewModel.participants.first { $0.isMe }, activityTitle: currentActivity?.title ?? "", activityId: currentActivity?.id ?? 0)
            }
            .overlay(alignment: .bottomTrailing) {
                /* Floating calculator button kept commented (handled via tab item)
                Button {
                    withAnimation { showPrizepool.toggle() }
                } label: {
                    Image(systemName: "calculator")
                        .font(.system(size: 16, weight: .semibold))
                        .foregroundColor(.white)
                        .padding(10)
                        .background(Color.black.opacity(0.6))
                        .clipShape(Circle())
                        .overlay(Circle().stroke(cyan.opacity(0.5), lineWidth: 1))
                }
                .padding(16)
                */
            }
        }
        .alert(isPresented: Binding<Bool>(get: { viewModel.alertMessage != nil }, set: { if !$0 { viewModel.alertMessage = nil } })) {
            Alert(title: Text("Attention"), message: Text(viewModel.alertMessage ?? ""), dismissButton: .default(Text("OK"), action: { viewModel.alertMessage = nil }))
        }
        .sheet(isPresented: $showHelp) {
            HelpView()
        }
    }

    // MARK: - Header
    private var headerSection: some View {
        HStack(alignment: .center, spacing: 12) {
            Image(systemName: "suit.spade.fill").font(.system(size:28)).foregroundColor(cyan).shadow(color: cyan.opacity(0.7), radius: 8)
            VStack(alignment: .leading, spacing: 2) {
                HStack(alignment:.firstTextBaseline, spacing:6) {
                    Text("CardEvent").font(.title2.bold()).foregroundColor(.white)
                    
                    let version = Bundle.main.infoDictionary?["CFBundleShortVersionString"] as? String ?? "1.0"
                    let build = Bundle.main.infoDictionary?["CFBundleVersion"] as? String ?? "1"
                    let majorVersion = version.split(separator: ".").first ?? "1"
                    Text("v\(majorVersion).\(build)")
                        .font(.caption)
                        .foregroundColor(.white.opacity(0.45))
                    
                    Button(action: { showHelp = true }) {
                        Image(systemName: "info.circle")
                            .font(.title3)
                            .foregroundColor(.cyan)
                    }
                }
                if !auth.pseudo.isEmpty {
                    Button { showPlayerProfile = true } label: {
                        HStack(spacing:4) { Text("Bonjour, \(auth.pseudo)").font(.subheadline).foregroundColor(cyan); Image(systemName: "chevron.right").font(.caption2).foregroundColor(cyan.opacity(0.6)) }
                    }.buttonStyle(.plain)
                }
            }
            Spacer()
            if auth.isAdmin { Text("Admin").font(.caption2.bold()).foregroundColor(.black).padding(.horizontal,8).padding(.vertical,4).background(gold).cornerRadius(8) }
            Button { showPlayerProfile = true } label: {
                Group {
                    if !photoUrl.isEmpty, let url = URL(string: photoUrl) { AsyncImage(url: url) { phase in switch phase { case .success(let img): img.resizable().scaledToFill(); default: avatarInitials } } }
                    else { avatarInitials }
                }
                .frame(width:44, height:44).clipShape(Circle()).overlay(Circle().stroke(cyan.opacity(0.5), lineWidth:2)).shadow(color: cyan.opacity(0.3), radius:6)
            }.buttonStyle(.plain)
        }.padding(.top, 8)
    }

    private var avatarInitials: some View {
        ZStack { Circle().fill(Color.white.opacity(0.12)); Text(auth.pseudo.prefix(2).uppercased()).font(.system(size:16, weight:.bold, design:.rounded)).foregroundColor(cyan) }
    }

    // MARK: - Activité card
    private var activityCard: some View {
        VStack(alignment:.leading, spacing:14) {
            Label("Prochaine partie / En cours", systemImage: "calendar.badge.clock").font(.caption.bold()).foregroundColor(cyan).textCase(.uppercase)
            Divider().background(cyan.opacity(0.3))
            if let act = currentActivity {
                HStack(alignment:.top, spacing:0) {
                    VStack(alignment:.leading, spacing:8) {
                        Text(act.title).font(.headline.bold()).foregroundColor(.white).lineLimit(2)
                        if let date = viewModel.nextActivityDateFull ?? viewModel.nextActivityDate { Label(date, systemImage: "clock").font(.subheadline).foregroundColor(gold) }
                        HStack(spacing:16) {
                            if let buyin = viewModel.nextActivityBuyin { statPill(icon:"dollarsign.circle", value:"\(buyin)€", label:"Buy-in", color: green) }
                            if let rake = viewModel.nextActivityRake { statPill(icon:"percent", value:"\(rake)€", label:"Rake", color: .orange) }
                            statPill(icon:"person.3", value:"\(viewModel.playerCount)", label:"Inscrits", color: purple)
                        }
                        HStack(spacing:8) { Circle().fill(viewModel.isRegistered ? green : Color.red.opacity(0.7)).frame(width:8,height:8); Text(viewModel.isRegistered ? "Vous êtes inscrit(e)" : "Pas encore inscrit(e)").font(.caption.bold()).foregroundColor(viewModel.isRegistered ? green : Color.red.opacity(0.9)) }
                    }
                    Spacer()
                    VStack(spacing:8) {
                        Button { viewModel.navigateActivity(by:1) } label: { Image(systemName: "chevron.right.circle").font(.title2).foregroundColor(viewModel.canGoToNextActivity ? cyan : .gray.opacity(0.3)) }.disabled(!viewModel.canGoToNextActivity)
                        Button { viewModel.navigateActivity(by:-1) } label: { Image(systemName: "chevron.left.circle").font(.title2).foregroundColor(viewModel.canGoToPrevActivity ? cyan : .gray.opacity(0.3)) }.disabled(!viewModel.canGoToPrevActivity)
                    }
                }
            } else {
                Text("Aucune activité disponible").foregroundColor(.secondary).frame(maxWidth:.infinity, alignment:.center).padding(.vertical,8)
            }
        }
        .padding(16).background(Color.white.opacity(0.05)).cornerRadius(16).overlay(RoundedRectangle(cornerRadius:16).stroke(cyan.opacity(0.25), lineWidth:1))
    }

    // MARK: - Calculateur prizepool
    private var prizepoolAdvice: (inscrits: Int, recaves: Int, amount: Int)? {
        guard let act = currentActivity else { return nil }
        
        let inscrits = act.count
        let recaves = act.recaves
        let totalBuyins = inscrits + recaves
        
        if totalBuyins == 0 { return nil }
        return (inscrits, recaves, (inscrits * act.buyin) + (recaves * act.recave_montant))
    }

    private var prizepoolCalculator: some View {
        VStack(alignment:.leading, spacing:14) {
            HStack {
                Label("Répartition du Prizepool", systemImage: "eurosign.circle").font(.caption.bold()).foregroundColor(cyan).textCase(.uppercase)
                Spacer()
                if let advice = prizepoolAdvice {
                    let desc = advice.recaves > 0 ? "\(advice.inscrits) buy-ins + \(advice.recaves) recaves" : "\(advice.inscrits) buy-ins"
                    Text("\(advice.amount)€ / \(desc)")
                        .font(.caption2.bold())
                        .foregroundColor(gold)
                }
            }
            Divider().background(cyan.opacity(0.3))
            HStack(spacing:12) {
                VStack(alignment:.leading, spacing:4) { Text("Pricepool").font(.caption2).foregroundColor(.white); TextField("Ex: 200", text: $prizepoolInput).keyboardType(.numberPad).frame(width:80).padding(8).background(Color.white.opacity(0.06)).cornerRadius(8).foregroundColor(.white) }
                VStack(alignment:.leading, spacing:4) { Text("Caves").font(.caption2).foregroundColor(.white); TextField("Ex: 20", text: $buyinsInput).keyboardType(.numberPad).frame(width:60).padding(8).background(Color.white.opacity(0.06)).cornerRadius(8).foregroundColor(.white) }
                VStack(alignment:.leading, spacing:4) { Text("+1 Payé").font(.caption2).foregroundColor(.white); Toggle("", isOn: $payExtraPlayer).tint(cyan).labelsHidden().frame(height:36).onChange(of: payExtraPlayer) { _ in calculateRepartition() } }
                Spacer()
                if auth.isAdmin || (!auth.pseudo.isEmpty && currentActivity?.organisateur == auth.pseudo) {
                    Button {
                        affecterGains()
                    } label: {
                        Text("Paye").font(.subheadline.bold()).foregroundColor(.white).padding(.horizontal,10).padding(.vertical,8).background(purple.opacity(0.8)).cornerRadius(8)
                    }
                }
                Button { calculateRepartition() } label: { Text("Calc").font(.subheadline.bold()).foregroundColor(.white).padding(.horizontal,14).padding(.vertical,8).background(cyan.opacity(0.85)).cornerRadius(8) }.disabled(prizepoolInput.isEmpty || buyinsInput.isEmpty)
            }
            if showResult && !repartition.isEmpty {
                VStack(alignment:.leading, spacing:6) {
                    Text("Répartition proposée :").font(.caption.bold()).foregroundColor(.secondary)
                    ForEach(repartition, id: \.place) { r in
                        HStack { Text("\(r.place)\(ordinalSuffix(r.place)) :").font(.subheadline).foregroundColor(.white); Spacer(); Text("\(r.gain) €").font(.subheadline.bold()).foregroundColor(gold) }
                    }
                }.padding(.top,8)
            }
        }
        .padding(16).background(Color.white.opacity(0.05)).cornerRadius(16).overlay(RoundedRectangle(cornerRadius:16).stroke(cyan.opacity(0.25), lineWidth:1))
    }

    // MARK: - Shortcuts grid (Raccourcis)
    private var shortcutsGrid: some View {
        VStack(alignment: .leading, spacing: 12) {
            Label("Raccourcis", systemImage: "square.grid.2x2")
                .font(.caption.bold())
                .foregroundColor(cyan)
                .textCase(.uppercase)

            Divider().background(cyan.opacity(0.3))

            LazyVGrid(columns: [GridItem(.flexible()), GridItem(.flexible())], spacing: 12) {
                liveTimerCard
                shortcutCard(icon: "info.circle", label: "Détails Partie", color: .orange) { showActivityInfo = true }
                shortcutCard(icon: "person.crop.circle", label: "Mon Profil / Traker", color: gold) { showPlayerProfile = true }
                shortcutCard(icon: hasResults ? "trophy" : "person.3", label: hasResults ? "Résultats" : "Liste participants", color: purple) { showParticipants = true }
            }
        }
        .padding(16)
        .background(Color.white.opacity(0.05))
        .cornerRadius(16)
        .overlay(RoundedRectangle(cornerRadius: 16).stroke(cyan.opacity(0.25), lineWidth: 1))
    }

    // MARK: - Options inscription
    private var optionsCard: some View {
        VStack(alignment:.leading, spacing:12) {
            Label("Options", systemImage: "slider.horizontal.3").font(.caption.bold()).foregroundColor(gold).textCase(.uppercase)
            Divider().background(gold.opacity(0.3))
            VStack(spacing:0) {
                optionToggleRow(icon: "eye.slash.fill", iconColor: .purple, title: "Anonyme", subtitle: "Votre nom ne sera pas affiché publiquement", binding: $regAnonymous, exclusiveOff: [])
                Divider().background(Color.white.opacity(0.08)).padding(.leading,44)
                optionToggleRow(icon: "star.fill", iconColor: .yellow, title: "Option", subtitle: "Inscription sous réserve de confirmation", binding: $regOption, exclusiveOff: [])
                Divider().background(Color.white.opacity(0.08)).padding(.leading,44)
                optionToggleRow(icon: "clock.arrow.circlepath", iconColor: .orange, title: "Latereg", subtitle: "Inscription tardive", binding: $regLatereg, exclusiveOff: [])
            }
            .background(Color.white.opacity(0.04)).cornerRadius(12)
        }
        .padding(16).background(Color.white.opacity(0.05)).cornerRadius(16).overlay(RoundedRectangle(cornerRadius:16).stroke(gold.opacity(0.2), lineWidth:1))
    }

    // MARK: - Live cardevent card
    private var liveTimerCard: some View {
        ZStack(alignment: .bottomTrailing) {
            Button { showLiveTimer = true } label: {
                VStack(spacing:6) {
                    if let act = currentActivity, act.startDate > Date() {
                        let s = Int(act.startDate.timeIntervalSinceNow)
                        let h = s / 3600
                        let m = (s % 3600) / 60
                        Text(String(format: "%02d:%02d", h, m)).font(.system(size:20, weight:.bold, design:.monospaced)).foregroundColor(cyan)
                        Text("Démarre dans").font(.caption2.bold()).foregroundColor(.white.opacity(0.6))
                    }
                    else if miniTimer.status == "loading" { ProgressView().tint(cyan).scaleEffect(0.8) }
                    else if miniTimer.status == "finished" { Image(systemName: "flag.checkered").font(.system(size:20)).foregroundColor(cyan); Text("Terminé").font(.caption2.bold()).foregroundColor(.white.opacity(0.6)) }
                    else if miniTimer.status == "idle" || miniTimer.levelName.isEmpty { Image(systemName: "timer").font(.system(size:22)).foregroundColor(cyan).shadow(color: cyan.opacity(0.5), radius:6) }
                    else {
                        ZStack { Circle().stroke(cyan.opacity(0.15), lineWidth:3); Circle().trim(from:0, to: miniTimer.progress).stroke(miniTimer.isPaused ? Color.orange : cyan, style: StrokeStyle(lineWidth:3, lineCap:.round)).rotationEffect(.degrees(-90)).animation(.linear(duration:1), value: miniTimer.secondsRemaining); Text(miniTimer.timeTensString).font(.system(size:11, weight:.bold, design:.monospaced)).foregroundColor(.white) }
                        .frame(width:44, height:44)
                        Text(miniTimer.blindsText).font(.system(size:10, weight:.semibold, design:.monospaced)).foregroundColor(cyan)
                    }
                    Text("Live Timer").font(.caption.bold()).foregroundColor(.white)
                }
                .frame(maxWidth:.infinity).padding(.vertical,12).background(Color.white.opacity(0.06)).cornerRadius(12)
            }
            .buttonStyle(.plain)
            .onAppear { if let id = currentActivity?.id { miniTimer.start(activityId: id) } }
            .onChange(of: currentActivity?.id) { newId in miniTimer.stop(); if let id = newId { miniTimer.start(activityId: id) } }

            // Small toggle button bottom-right to open prizepool calculator
            /* Button {
                withAnimation { showPrizepool.toggle() }
            } label: {
                Image(systemName: "calculator")
                    .font(.system(size: 14, weight: .semibold))
                    .foregroundColor(.white)
                    .padding(8)
                    .background(Color.black.opacity(0.5))
                    .clipShape(Circle())
                    .overlay(Circle().stroke(cyan.opacity(0.99), lineWidth: 1))
            } */
            .padding(6)
        }
    }

    private func shortcutCard(icon: String, label: String, color: Color, action: @escaping ()->Void) -> some View {
        Button(action: action) {
            VStack(spacing:8) { Image(systemName: icon).font(.system(size:24)).foregroundColor(color).shadow(color: color.opacity(0.5), radius:6); Text(label).font(.caption.bold()).foregroundColor(.white) }
            .frame(maxWidth:.infinity).padding(.vertical,16).background(Color.white.opacity(0.06)).cornerRadius(12)
        }.buttonStyle(.plain)
    }

    // MARK: - Inscription rapide
    private var registrationCard: some View {
        VStack(alignment:.leading, spacing:14) {
            Label("Action rapide", systemImage: "bolt.circle.fill").font(.caption.bold()).foregroundColor(.orange).textCase(.uppercase)
            Divider().background(Color.orange.opacity(0.3))
            HStack(spacing:12) {
                Text(viewModel.isRegistered ? "Vous êtes inscrit(e)" : "Rejoindre la partie ?").font(.subheadline.bold()).foregroundColor(.white)
                Spacer()
                if !showOptions { Button { withAnimation { showOptions = true } } label: { Text(viewModel.isRegistered ? "Modifier Inscription" : "S'inscrire").font(.subheadline.bold()).foregroundColor(.white).padding(.horizontal,14).padding(.vertical,10).background(viewModel.isRegistered ? Color.orange.opacity(0.8) : green.opacity(0.85)).cornerRadius(10) }.disabled(auth.pseudo.isEmpty) }
            }
            if showOptions {
                HStack(spacing:10) {
                    Button {
                        Task { await viewModel.registerWithOptions(anonyme: regAnonymous, option: regOption, latereg: regLatereg); withAnimation { showOptions = false } }
                    } label: {
                        if viewModel.isRegistering { ProgressView().tint(.white).frame(maxWidth:.infinity).padding(.vertical,10) }
                        else { Text(viewModel.isRegistered ? "Valider" : "Confirmer").font(.subheadline.bold()).foregroundColor(.white).frame(maxWidth:.infinity).padding(.vertical,10).background(green.opacity(0.85)).cornerRadius(10) }
                    }.disabled(viewModel.isRegistering || auth.pseudo.isEmpty)

                    Button {
                        if viewModel.isRegistered { withAnimation { showOptions = false }; Task { await viewModel.toggleRegistration() } }
                        else { withAnimation { showOptions = false } }
                    } label: { Text(viewModel.isRegistered ? "Désinscrire" : "Annuler").font(.subheadline.bold()).foregroundColor(.white).frame(maxWidth:.infinity).padding(.vertical,10).background(Color.red.opacity(0.75)).cornerRadius(10) }
                    .disabled(viewModel.isRegistering)
                }
            }
        }
        .padding(16).background(Color.white.opacity(0.05)).cornerRadius(16).overlay(RoundedRectangle(cornerRadius:16).stroke(Color.orange.opacity(0.25), lineWidth:1))
    }

    // MARK: - Podium des payés (affiché lorsque la partie est terminée)
    private var podiumCard: some View {
        VStack(alignment: .leading, spacing: 2) {
            Label("Podium payés", systemImage: "crown.fill").font(.caption.bold()).foregroundColor(gold).textCase(.uppercase)
            Divider().background(gold.opacity(0.3))

            if paidParticipants.isEmpty {
                Text("Aucun joueur payé").foregroundColor(.secondary)
            } else {
                ForEach(paidParticipants, id: \.id) { p in
                    HStack(spacing: 4) {
                        VStack(alignment:.leading, spacing:0) {
                            Text(pseudoOrUnknown(p.pseudo)).font(.subheadline.bold()).foregroundColor(.white).lineLimit(1)
                        }
                        Spacer()
                        Text(formatEur(p.gain)).font(.subheadline.bold()).foregroundColor(.green)
                    }
                    .padding(.vertical, 2)
                    .fixedSize(horizontal: false, vertical: true)
                }
            }
        }
        .padding(16)
        .background(Color.white.opacity(0.05)).cornerRadius(16).overlay(RoundedRectangle(cornerRadius:16).stroke(gold.opacity(0.25), lineWidth:1))
    }

    private func pseudoOrUnknown(_ p: String?) -> String {
        if let s = p, !s.isEmpty { return s }
        return "(inconnu)"
    }

    private func formatEur(_ v: Int) -> String {
        return String(format: "%d €", v)
    }

    // MARK: - Helpers
    private func ordinalSuffix(_ n: Int) -> String { n == 1 ? "er" : "e" }
    
    private func affecterGains() {
        guard let act = currentActivity else { return }
        
        let p2 = viewModel.participants.first { $0.classement == 2 }
        if p2 == nil || p2?.gain != 0 {
            viewModel.alertMessage = "La partie n'est pas terminée (classement 2 introuvable ou gain déjà affecté)."
            return
        }
        
        guard !repartition.isEmpty else {
            viewModel.alertMessage = "Veuillez d'abord calculer la répartition."
            return
        }
        
        var gainsDict: [String: Int] = [:]
        for r in repartition {
            gainsDict["\(r.place)"] = r.gain
        }
        
        let payload: [String: Any] = [
            "activity_id": act.id,
            "gains": gainsDict
        ]
        
        guard let url = URL(string: "https://viendez.com/api/affect-gains.php") else { return }
        var request = URLRequest(url: url)
        request.httpMethod = "POST"
        request.setValue("application/json", forHTTPHeaderField: "Content-Type")
        if let token = AuthService.shared.token {
            request.setValue("Bearer \(token)", forHTTPHeaderField: "Authorization")
        }
        
        request.httpBody = try? JSONSerialization.data(withJSONObject: payload)
        
        Task {
            do {
                let (data, response) = try await URLSession.shared.data(for: request)
                if let httpRes = response as? HTTPURLResponse, httpRes.statusCode == 200 {
                    if let json = try? JSONSerialization.jsonObject(with: data) as? [String: Any],
                       let success = json["success"] as? Bool, success {
                        await MainActor.run {
                            viewModel.alertMessage = "✅ Gains affectés avec succès !"
                        }
                        await viewModel.fetchParticipants(activityId: act.id)
                    } else {
                        let msg = (try? JSONSerialization.jsonObject(with: data) as? [String: Any])?["error"] as? String ?? "Erreur inconnue"
                        await MainActor.run { viewModel.alertMessage = "Erreur: \(msg)" }
                    }
                } else {
                    await MainActor.run { viewModel.alertMessage = "Erreur serveur." }
                }
            } catch {
                await MainActor.run { viewModel.alertMessage = "Erreur réseau: \(error.localizedDescription)" }
            }
        }
    }

    private func calculateRepartition() {
        hideKeyboard()
        guard let prizepool = Int(prizepoolInput), let buyins = Int(buyinsInput), prizepool > 0, buyins > 0 else { repartition = []; showResult = false; return }

        var places: Int
        if buyins > 20 { places = 5 }
        else if buyins >= 16 { places = 4 }
        else if buyins >= 12 { places = 3 }
        else { places = 2 }
        
        if payExtraPlayer {
            places += 1
        }
        
        let percents: [Int]
        switch places {
        case 6: percents = [32, 22, 16, 13, 10, 7]
        case 5: percents = [35, 25, 18, 12, 10]
        case 4: percents = [40, 30, 20, 10]
        case 3: percents = [50, 30, 20]
        case 2: percents = [65, 35]
        default: percents = [100]
        }

        let targetTotal = (prizepool / 10) * 10
        let raw = percents.map { Double(prizepool) * Double($0) / 100.0 }
        var gains = raw.map { Int(floor($0 / 10.0)) * 10 }
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

    private func loadPhoto() async {
        guard !auth.pseudo.isEmpty, let encoded = auth.pseudo.addingPercentEncoding(withAllowedCharacters: .urlQueryAllowed), let url = URL(string: "https://viendez.com/api/player-stats.php?pseudo=\(encoded)") else { return }
        if let (data, _) = try? await URLSession.shared.data(from: url), let json = try? JSONSerialization.jsonObject(with: data) as? [String:Any], let p = json["photo_url"] as? String, !p.isEmpty { photoUrl = p }
    }

    private func statPill(icon: String, value: String, label: String, color: Color) -> some View {
        VStack(spacing:2) { HStack(spacing:3) { Image(systemName: icon).font(.caption2); Text(value).font(.caption.bold()) }.foregroundColor(color); Text(label).font(.system(size:9)).foregroundColor(.secondary) }
    }

    private func optionToggleRow(icon: String, iconColor: Color, title: String, subtitle: String, binding: Binding<Bool>, exclusiveOff: [Binding<Bool>]) -> some View {
        HStack(spacing:12) {
            Image(systemName: icon).font(.system(size:16)).foregroundColor(binding.wrappedValue ? iconColor : .gray.opacity(0.4)).frame(width:28)
            VStack(alignment:.leading, spacing:2) { Text(title).font(.subheadline.bold()).foregroundColor(.white); Text(subtitle).font(.caption2).foregroundColor(.secondary) }
            Spacer()
            Toggle("", isOn: binding).tint(cyan).labelsHidden()
        }
        .padding(.horizontal,12).padding(.vertical,10)
    }
}
import SwiftUI

struct HelpView: View {
    @Environment(\.dismiss) var dismiss
    
    // Contenu Markdown du guide utilisateur
    let guideContent: LocalizedStringKey = """
    # Guide d'Utilisation - CardEvent

    Bienvenue dans l'application **CardEvent** ! Ce guide vous présente les différentes fonctionnalités de l'application et vous explique comment les utiliser au quotidien.

    ---

    ## 1. Connexion à l'application
    Lors de l'ouverture de l'application, vous arriverez sur l'écran de connexion.
    - **Identifiants** : Saisissez votre adresse e-mail et votre mot de passe habituels (ceux utilisés sur le site viendez.com).
    - **Connexion** : Appuyez sur le bouton de connexion pour accéder à votre espace personnel.
    *Note : Si vous n'avez pas de compte, il faudra d'abord vous inscrire via la page d'inscription dédiée.*

    ---

    ## 2. Écran Principal (Accueil & Activités)
    C'est le tableau de bord de votre application. Il vous permet de suivre l'actualité de vos événements de cartes ou de poker.

    ### Découvrir le prochain événement
    - L'application affiche automatiquement les détails de la **prochaine activité** prévue (date, heure, lieu).
    - Vous verrez également la liste des autres participants déjà inscrits.

    ### S'inscrire ou se désinscrire
    - **Bouton d'inscription** : Si vous n'êtes pas encore inscrit au prochain événement, un bouton "S'inscrire" sera visible. Appuyez dessus pour confirmer votre présence.
    - **Annulation** : Une fois inscrit, le bouton change d'état. Si vous avez un empêchement, vous pouvez utiliser ce même bouton pour vous désinscrire et libérer votre place.

    ---

    ## 3. Profil du Joueur et Statistiques
    Accédez à votre espace personnel en vous rendant dans la section **Profil**.

    ### Vos Statistiques
    - Retrouvez un résumé complet de vos performances : nombre de parties jouées, classements, points cumulés, etc.

    ### Se déconnecter
    - Tout en bas de votre profil, vous trouverez le bouton **"Se déconnecter"** (en rouge). 
    - Utilisez-le si vous souhaitez fermer votre session, particulièrement si vous partagez votre appareil avec quelqu'un d'autre.

    ---

    ## 4. Tickets de Tombola
    CardEvent récompense votre participation avec des tickets de tombola virtuels !

    - **Consulter vos tickets** : Depuis votre Profil, appuyez sur le bouton **"Voir tickets"**.
    - **Organisation** : Vos tickets sont affichés de manière claire et classés par **mois**. 
    - Vous pouvez dérouler ou réduire chaque mois pour voir le détail des tickets accumulés au fil du temps.

    ---

    ## 5. Classements et Fonctionnalités supplémentaires
    - **Classement Général (Challenge)** : L'application vous permet de vous comparer aux autres joueurs et de voir votre position dans le classement général du défi en cours.

    ---
    *Si vous rencontrez le moindre problème lors de l'utilisation de l'application (bouton qui ne réagit pas, liste qui ne charge pas), n'hésitez pas à vérifier votre connexion internet ou à relancer l'application.*

    [**➡️ Accéder au formulaire de contact et d'assistance**](https://viendez.com/assist.php)
    """
    
    var body: some View {
        NavigationView {
            ScrollView {
                VStack(alignment: .leading) {
                    Text(guideContent)
                        .padding()
                        .font(.body)
                }
            }
            .navigationTitle("Aide & Fonctionnalités")
            .navigationBarTitleDisplayMode(.inline)
            .toolbar {
                ToolbarItem(placement: .navigationBarTrailing) {
                    Button("Fermer") { dismiss() }
                }
            }
        }
    }
}

struct HelpView_Previews: PreviewProvider {
    static var previews: some View {
        HelpView()
    }
}
