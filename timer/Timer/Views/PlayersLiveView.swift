import SwiftUI

// MARK: - Model

private struct LiveParticipant: Identifiable {
    let id = UUID()
    let pseudo: String
    let statut: String
    let recave: Int
    let bounty: Int
    let classement: Int
    let gain: Int
    let eliminatedBy: [String]
    var isEliminated: Bool { classement > 0 || statut.lowercased().contains("elimin") }
}

private struct PlayersAPIResponse: Decodable {
    let success: Bool
    let count: Int?
    let participants: [RawParticipant]?
    let activity_title: String?
    let buyin: Int?
    let rake: Int?

    struct RawParticipant: Decodable {
        let pseudo: String
        let statut: String?
        let recave: Int?
        let bounty: Int?
        let classement: Int?
        let gain: Int?
    }
}

// MARK: - ViewModel

@MainActor
private final class PlayersLiveViewModel: ObservableObject {
    @Published var participants: [LiveParticipant] = []
    @Published var activityTitle: String = ""
    @Published var buyin: Int = 0
    @Published var rake: Int = 0
    @Published var isLoading: Bool = true

    private var syncTimer: Timer?
    private let activityId: Int
    private let token: String?

    var activePlayers: Int { participants.filter { !$0.isEliminated }.count }
    var totalPlayers: Int { participants.count }
    var totalRecaves: Int { participants.reduce(0) { $0 + $1.recave } }
    var pricePool: Int { totalPlayers * buyin + totalRecaves * rake }

    init(activityId: Int) {
        self.activityId = activityId
        self.token = AuthService.shared.token
    }

    func start() {
        Task { await sync() }
        syncTimer = Timer.scheduledTimer(withTimeInterval: 5, repeats: true) { [weak self] _ in
            Task { await self?.sync() }
        }
    }

    func stop() {
        syncTimer?.invalidate()
        syncTimer = nil
    }

    private func sync() async {
        guard let url = URL(string: "https://viendez.com/api/participants-list.php?activity_id=\(activityId)") else { return }
        var request = URLRequest(url: url)
        request.timeoutInterval = 10
        if let token { request.setValue("Bearer \(token)", forHTTPHeaderField: "Authorization") }

        do {
            let (data, _) = try await URLSession.shared.data(for: request)
            guard let json = try? JSONSerialization.jsonObject(with: data) as? [String: Any],
                  let success = json["success"] as? Bool, success,
                  let list = json["participants"] as? [[String: Any]] else { return }

            activityTitle = json["activity_title"] as? String ?? activityTitle
            buyin = json["buyin"] as? Int ?? buyin
            rake  = json["rake"]  as? Int ?? rake

            participants = list.map { p in
                LiveParticipant(
                    pseudo:       p["pseudo"]      as? String ?? "",
                    statut:       p["statut"]      as? String ?? "",
                    recave:       p["recave"]      as? Int    ?? 0,
                    bounty:       p["bounty"]      as? Int    ?? 0,
                    classement:   p["classement"]  as? Int    ?? 0,
                    gain:         p["gain"]        as? Int    ?? 0,
                    eliminatedBy: []
                )
            }
            .sorted {
                let a = $0.classement == 0 ? Int.max : $0.classement
                let b = $1.classement == 0 ? Int.max : $1.classement
                return a < b
            }

            isLoading = false
        } catch {
            isLoading = false
        }
    }
}

// MARK: - View

struct PlayersLiveView: View {
    let activityId: Int
    let activityTitle: String
    @StateObject private var vm: PlayersLiveViewModel
    @Environment(\.dismiss) private var dismiss

    private let cyanColor = Color(red: 0, green: 0.82, blue: 1)

    init(activityId: Int, activityTitle: String) {
        self.activityId = activityId
        self.activityTitle = activityTitle
        _vm = StateObject(wrappedValue: PlayersLiveViewModel(activityId: activityId))
    }

    var body: some View {
        ZStack {
            Color.black.ignoresSafeArea()

            if vm.isLoading {
                ProgressView().tint(cyanColor).scaleEffect(2)
            } else {
                VStack(spacing: 0) {
                    header
                    Divider().background(Color.white.opacity(0.15))
                    columnHeaders
                    Divider().background(Color.white.opacity(0.15))
                    playersList
                    Divider().background(Color.white.opacity(0.15))
                    statsFooter
                }
            }
        }
        .navigationBarTitleDisplayMode(.inline)
        .toolbar {
            ToolbarItem(placement: .principal) {
                VStack(spacing: 1) {
                    Text("Joueurs")
                        .font(.headline.bold())
                        .foregroundColor(cyanColor)
                    if !activityTitle.isEmpty {
                        Text(activityTitle)
                            .font(.caption2)
                            .foregroundColor(.white.opacity(0.5))
                            .lineLimit(1)
                    }
                }
            }
            ToolbarItem(placement: .navigationBarTrailing) {
                Button { dismiss() } label: {
                    Image(systemName: "xmark.circle.fill")
                        .foregroundColor(.white.opacity(0.6))
                }
            }
        }
        .preferredColorScheme(.dark)
        .onAppear { vm.start() }
        .onDisappear { vm.stop() }
    }

    // MARK: - Subviews

    private var header: some View {
        HStack {
            Image(systemName: "arrow.clockwise")
                .font(.caption2)
                .foregroundColor(cyanColor.opacity(0.6))
            Text("Sync auto · 5s")
                .font(.caption2)
                .foregroundColor(.white.opacity(0.3))
            Spacer()
            Text("\(vm.activePlayers) actifs / \(vm.totalPlayers) inscrits")
                .font(.caption.bold())
                .foregroundColor(cyanColor)
        }
        .padding(.horizontal, 16)
        .padding(.vertical, 8)
    }

    private var columnHeaders: some View {
        HStack(spacing: 0) {
            Text("#").frame(width: 38, alignment: .center)
            Text("Joueur").frame(maxWidth: .infinity, alignment: .leading)
            Text("Recave").frame(width: 56, alignment: .center)
            Text("Bounty").frame(width: 56, alignment: .center)
            Text("Gains").frame(width: 58, alignment: .trailing)
        }
        .font(.caption2.bold())
        .foregroundColor(.white.opacity(0.4))
        .textCase(.uppercase)
        .padding(.horizontal, 12)
        .padding(.vertical, 6)
    }

    private var playersList: some View {
        ScrollView {
            LazyVStack(spacing: 0) {
                ForEach(Array(vm.participants.enumerated()), id: \.element.id) { _, p in
                    playerRow(p)
                    Divider().padding(.leading, 50).opacity(0.3)
                }
            }
        }
    }

    private func playerRow(_ p: LiveParticipant) -> some View {
        let eliminated = p.isEliminated
        return HStack(spacing: 0) {
            // Rang
            Group {
                if p.classement > 0 {
                    Text("#\(p.classement)")
                        .foregroundColor(rankColor(p.classement))
                } else {
                    Text("–").foregroundColor(.white.opacity(0.25))
                }
            }
            .font(.system(size: 15, weight: .bold, design: .rounded))
            .frame(width: 38, alignment: .center)

            // Nom
            Text(p.pseudo)
                .font(.system(size: 16, weight: eliminated ? .regular : .semibold))
                .foregroundColor(eliminated ? .red.opacity(0.75) : .white)
                .lineLimit(1)
                .frame(maxWidth: .infinity, alignment: .leading)

            // Recave
            Text(p.recave > 0 ? "\(p.recave)" : "–")
                .font(.system(size: 14, weight: .semibold, design: .rounded))
                .foregroundColor(p.recave > 0 ? .orange : .white.opacity(0.2))
                .frame(width: 56, alignment: .center)

            // Bounty
            Text(p.bounty > 0 ? "\(p.bounty)" : "–")
                .font(.system(size: 14, weight: .semibold, design: .rounded))
                .foregroundColor(p.bounty > 0 ? Color.purple.opacity(0.9) : .white.opacity(0.2))
                .frame(width: 56, alignment: .center)

            // Gains
            Text(p.gain != 0 ? "\(p.gain)€" : "–")
                .font(.system(size: 14, weight: .semibold, design: .rounded))
                .foregroundColor(p.gain > 0 ? .green : p.gain < 0 ? .red : .white.opacity(0.2))
                .frame(width: 58, alignment: .trailing)
        }
        .padding(.horizontal, 12)
        .padding(.vertical, 10)
        .background(eliminated ? Color.red.opacity(0.07) : Color.clear)
    }

    private func rankColor(_ rank: Int) -> Color {
        switch rank {
        case 1: return .yellow
        case 2: return Color(white: 0.8)
        case 3: return Color(red: 0.8, green: 0.5, blue: 0.2)
        default: return .white.opacity(0.4)
        }
    }

    private var statsFooter: some View {
        HStack(spacing: 20) {
            statItem(label: "Joueurs", value: "\(vm.activePlayers)/\(vm.totalPlayers)")
            statItem(label: "Recaves", value: "\(vm.totalRecaves)")
            if vm.pricePool > 0 {
                statItem(label: "Pricepool", value: "\(vm.pricePool) €")
            }
        }
        .padding(.horizontal, 16)
        .padding(.vertical, 12)
    }

    private func statItem(label: String, value: String) -> some View {
        VStack(spacing: 2) {
            Text(value)
                .font(.system(size: 16, weight: .bold, design: .rounded))
                .foregroundColor(.white)
            Text(label)
                .font(.caption2)
                .foregroundColor(.white.opacity(0.4))
                .textCase(.uppercase)
                .tracking(1)
        }
    }
}
