import SwiftUI

// MARK: - Model
struct PlayerMovement: Identifiable {
    let id = UUID()
    let rank: Int
    let name: String
    let memberId: Int
    let bountyCount: Int
    let recaves: Int
    let eliminatedBy: [String]
    let tickets: String
    let isEliminated: Bool
    let phoneticName: String
    let classement: Int
}

// MARK: - ViewModel
@MainActor
private final class PlayersMovementsVM: ObservableObject {
    @Published var players: [PlayerMovement] = []
    @Published var isLoading = true
    @Published var totalPlayers = 0
    @Published var activePlayers = 0
    @Published var totalRecaves = 0
    @Published var pricepool = 0
    @Published var errorMessage: String?
    
    private var pollingTimer: Timer?
    private(set) var activityId: Int = 0
    private(set) var activityTitle: String = ""
    private var lastChecksum: String = ""

    func load(activityId: Int, title: String) async {
        self.activityId = activityId
        self.activityTitle = title
        await fetchPlayers()
        
        // Setup polling for updates
        pollingTimer = Timer.scheduledTimer(withTimeInterval: 5, repeats: true) { [weak self] _ in
            Task { await self?.checkForUpdates() }
        }
    }

    func unload() {
        pollingTimer?.invalidate()
        pollingTimer = nil
    }

    private func fetchPlayers() async {
        guard activityId > 0 else { return }
        
        guard let url = URL(string: "https://viendez.com/api/get-players-movements.php?uid=\(activityId)") else { return }
        
        do {
            let (data, _) = try await URLSession.shared.data(from: url)
            struct Response: Decodable {
                let success: Bool
                let players: [PlayerData]?
                let stats: StatsData?
                let message: String?
            }
            struct PlayerData: Decodable {
                let rank: Int
                let name: String
                let member_id: Int
                let bounty_count: Int
                let recaves: Int
                let eliminated_by: [String]
                let tickets: String
                let is_eliminated: Bool
                let phonetic_name: String
                let classement: Int
            }
            struct StatsData: Decodable {
                let total_players: Int
                let active_players: Int
                let total_recaves: Int
                let pricepool: Int
            }
            
            let resp = try JSONDecoder().decode(Response.self, from: data)
            if resp.success, let playerData = resp.players, let stats = resp.stats {
                self.players = playerData.map { p in
                    PlayerMovement(
                        rank: p.rank,
                        name: p.name,
                        memberId: p.member_id,
                        bountyCount: p.bounty_count,
                        recaves: p.recaves,
                        eliminatedBy: p.eliminated_by,
                        tickets: p.tickets,
                        isEliminated: p.is_eliminated,
                        phoneticName: p.phonetic_name,
                        classement: p.classement
                    )
                }
                self.totalPlayers = stats.total_players
                self.activePlayers = stats.active_players
                self.totalRecaves = stats.total_recaves
                self.pricepool = stats.pricepool
            } else {
                self.errorMessage = resp.message ?? "Erreur de chargement"
            }
        } catch {
            self.errorMessage = "Erreur: \(error.localizedDescription)"
        }
        
        isLoading = false
    }

    private func checkForUpdates() async {
        guard activityId > 0 else { return }
        guard let url = URL(string: "https://viendez.com/panel/fullscreen-player.php?uid=\(activityId)&check_updates=1") else { return }
        
        do {
            let (data, _) = try await URLSession.shared.data(from: url)
            struct CheckResp: Decodable {
                let checksum: String
            }
            let resp = try JSONDecoder().decode(CheckResp.self, from: data)
            if resp.checksum != lastChecksum {
                lastChecksum = resp.checksum
                await fetchPlayers()
            }
        } catch {}
    }
}

// MARK: - View
struct PlayersMovementsView: View {
    let activityId: Int
    let activityTitle: String
    
    @Environment(\.dismiss) private var dismiss
    @StateObject private var movementsVM = PlayersMovementsVM()
    
    private let cyan = Color(red: 0, green: 0.82, blue: 1)
    private let gold = Color(red: 1, green: 0.84, blue: 0)
    private let green = Color(red: 0.18, green: 0.85, blue: 0.46)

    var body: some View {
        NavigationView {
            ZStack {
                Color.black.ignoresSafeArea()
                
                VStack(spacing: 0) {
                    // Header
                    VStack(alignment: .leading, spacing: 12) {
                        HStack {
                            Text(activityTitle)
                                .font(.headline.bold())
                                .foregroundColor(cyan)
                                .lineLimit(2)
                            Spacer()
                            Button(action: { dismiss() }) {
                                Image(systemName: "xmark.circle.fill")
                                    .font(.title2)
                                    .foregroundColor(cyan.opacity(0.6))
                            }
                        }
                        Divider().background(cyan.opacity(0.3))
                    }
                    .padding(16)
                    
                    if movementsVM.isLoading {
                        VStack(spacing: 16) {
                            ProgressView()
                                .tint(cyan)
                            Text("Chargement des mouvements…")
                                .foregroundColor(.secondary)
                        }
                        .frame(maxWidth: .infinity, maxHeight: .infinity)
                    } else if let error = movementsVM.errorMessage {
                        VStack(spacing: 12) {
                            Image(systemName: "exclamationmark.triangle")
                                .font(.system(size: 40))
                                .foregroundColor(.red)
                            Text("Erreur")
                                .font(.headline)
                            Text(error)
                                .font(.subheadline)
                                .foregroundColor(.secondary)
                                .multilineTextAlignment(.center)
                        }
                        .frame(maxWidth: .infinity, maxHeight: .infinity)
                        .padding(32)
                    } else {
                        ScrollView {
                            VStack(spacing: 16) {
                                // Tableau des joueurs
                                VStack(spacing: 1) {
                                    // En-tête
                                    HStack(spacing: 0) {
                                        Text("#")
                                            .font(.caption.bold())
                                            .foregroundColor(.gray)
                                            .frame(width: 40, alignment: .center)
                                        Text("Joueur")
                                            .font(.caption.bold())
                                            .foregroundColor(.gray)
                                            .frame(maxWidth: .infinity, alignment: .leading)
                                        Text("Bounty")
                                            .font(.caption.bold())
                                            .foregroundColor(.gray)
                                            .frame(width: 50, alignment: .center)
                                        Text("Recave")
                                            .font(.caption.bold())
                                            .foregroundColor(.gray)
                                            .frame(width: 50, alignment: .center)
                                        Text("Tickets")
                                            .font(.caption.bold())
                                            .foregroundColor(.gray)
                                            .frame(width: 50, alignment: .center)
                                    }
                                    .padding(.horizontal, 12)
                                    .padding(.vertical, 8)
                                    .background(Color.white.opacity(0.05))
                                    
                                    Divider().background(cyan.opacity(0.2))
                                    
                                    // Lignes
                                    ForEach(movementsVM.players) { player in
                                        playerRow(player)
                                        Divider().background(cyan.opacity(0.1))
                                    }
                                }
                                .background(Color.white.opacity(0.02))
                                .cornerRadius(12)
                                .overlay(RoundedRectangle(cornerRadius: 12).stroke(cyan.opacity(0.2), lineWidth: 1))
                                
                                // Stats footer
                                VStack(spacing: 12) {
                                    HStack(spacing: 16) {
                                        statItem(label: "Joueurs", value: "\(movementsVM.activePlayers) / \(movementsVM.totalPlayers)")
                                        Spacer()
                                        statItem(label: "Recaves", value: "\(movementsVM.totalRecaves)")
                                        Spacer()
                                        statItem(label: "Prizepool", value: "\(movementsVM.pricepool)€")
                                    }
                                }
                                .padding(12)
                                .background(Color.white.opacity(0.05))
                                .cornerRadius(8)
                                
                                Spacer(minLength: 20)
                            }
                            .padding(16)
                        }
                    }
                }
            }
            .navigationBarHidden(true)
        }
        .task {
            await movementsVM.load(activityId: activityId, title: activityTitle)
        }
        .onDisappear {
            movementsVM.unload()
        }
    }
    
    private func playerRow(_ player: PlayerMovement) -> some View {
        HStack(spacing: 0) {
            // Rang
            VStack {
                if player.isEliminated && player.classement > 0 {
                    Text("\(player.classement)")
                        .font(.caption.bold())
                        .foregroundColor(gold)
                } else if player.isEliminated {
                    Image(systemName: "xmark")
                        .font(.caption.bold())
                        .foregroundColor(.red.opacity(0.7))
                } else {
                    Text("\(player.rank)")
                        .font(.caption.bold())
                        .foregroundColor(gold)
                }
            }
            .frame(width: 40, alignment: .center)
            
            // Joueur
            VStack(alignment: .leading, spacing: 2) {
                Text(player.name)
                    .font(.subheadline.bold())
                    .foregroundColor(player.isEliminated ? .red.opacity(0.7) : .white)
                    .lineLimit(1)
                if !player.eliminatedBy.isEmpty {
                    Text("Par: \(player.eliminatedBy.joined(separator: ", "))")
                        .font(.caption2)
                        .foregroundColor(.gray)
                        .lineLimit(1)
                }
            }
            .frame(maxWidth: .infinity, alignment: .leading)
            
            // Bounty
            Text(player.bountyCount > 0 ? "\(player.bountyCount)" : "-")
                .font(.caption.bold())
                .foregroundColor(player.bountyCount > 0 ? .cyan : .gray.opacity(0.5))
                .frame(width: 50, alignment: .center)
            
            // Recave
            Text(player.recaves > 0 ? "\(player.recaves)" : "-")
                .font(.caption.bold())
                .foregroundColor(player.recaves > 0 ? .orange : .gray.opacity(0.5))
                .frame(width: 50, alignment: .center)
            
            // Tickets
            Text(player.tickets.isEmpty ? "-" : player.tickets)
                .font(.caption2)
                .foregroundColor(.gray)
                .frame(width: 50, alignment: .center)
                .lineLimit(1)
        }
        .padding(.horizontal, 12)
        .padding(.vertical, 10)
        .background(player.isEliminated ? Color.red.opacity(0.1) : Color.white.opacity(0.02))
    }
    
    private func statItem(label: String, value: String) -> some View {
        VStack(spacing: 4) {
            Text(label)
                .font(.caption2)
                .foregroundColor(.gray)
            Text(value)
                .font(.subheadline.bold())
                .foregroundColor(cyan)
        }
    }
}

#Preview {
    PlayersMovementsView(
        viewModel: PokerTimerViewModel(),
        activityId: 1,
        activityTitle: "Texas Hold'em - Samedi"
    )
}
