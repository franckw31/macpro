import SwiftUI

// MARK: - Model

private struct ChallengeEntry: Identifiable {
    let id: Int          // id_membre
    let rank: Int
    let pseudo: String
    let nbParticipations: Int
    let tf: Int
    let nbVictoires: Int
    let cagnotte: Double
    let points: Int
}

// MARK: - ChallengeRankingView

struct ChallengeRankingView: View {
    private let baseURL = "https://viendez.com/api/challenge-ranking.php"

    let activityId: Int
    let myPseudo: String
    @Environment(\.dismiss) private var dismiss
    @State private var entries: [ChallengeEntry] = []
    @State private var challengeTitle: String = "Challenge"
    @State private var isLoading = false
    @State private var errorMessage: String? = nil
    @State private var showChallengePicker = false
    @State private var availableChallenges: [(id: Int, title: String)] = []
    @State private var selectedChallengeId: Int? = nil
    @State private var isLoadingChallenges: Bool = false
    @State private var challengesError: String? = nil

    var body: some View {
        // Main view layout
        NavigationView {
            VStack(spacing: 0) {
                // header handled by NavigationBar

                Group {
                    if isLoading {
                        VStack(spacing: 16) {
                            ProgressView()
                            Text("Chargement…")
                                .foregroundColor(.secondary)
                        }
                        .frame(maxWidth: .infinity, maxHeight: .infinity)

                    } else if let err = errorMessage {
                        VStack(spacing: 12) {
                            Image(systemName: "exclamationmark.triangle")
                                .font(.system(size: 44))
                                .foregroundColor(.orange)
                            Text(err)
                                .foregroundColor(.secondary)
                                .multilineTextAlignment(.center)
                                .padding(.horizontal)
                            Button("Réessayer") { Task { await load() } }
                                .buttonStyle(.borderedProminent)
                        }
                        .frame(maxWidth: .infinity, maxHeight: .infinity)

                    } else if entries.isEmpty {
                        VStack(spacing: 12) {
                            Image(systemName: "trophy")
                                .font(.system(size: 44))
                                .foregroundColor(.secondary)
                            Text("Aucun classement disponible")
                                .foregroundColor(.secondary)
                        }
                        .frame(maxWidth: .infinity, maxHeight: .infinity)

                    } else {
                        ScrollView {
                            columnHeader
                            Divider()
                            LazyVStack(spacing: 0) {
                                ForEach(entries) { entry in
                                    rowView(entry)
                                    Divider().padding(.leading, 16)
                                }
                            }
                        }
                    }
                }

                Spacer(minLength: 0)
            }
            .navigationTitle(challengeTitle)
            .navigationBarTitleDisplayMode(.inline)
            .navigationBarBackButtonHidden(true)
            .toolbar {
                ToolbarItem(placement: .navigationBarTrailing) {
                    Button(action: { showChallengePicker = true }) {
                        Image(systemName: "list.bullet")
                    }
                }
            }
            .sheet(isPresented: $showChallengePicker) {
                NavigationView {
                    Group {
                        if isLoadingChallenges {
                            VStack(spacing: 20) {
                                ProgressView()
                                Text("Chargement des challenges...")
                                    .foregroundColor(.secondary)
                            }
                            .frame(maxWidth: .infinity, maxHeight: .infinity)
                        } else if let err = challengesError {
                            VStack(spacing: 16) {
                                Image(systemName: "exclamationmark.triangle")
                                    .font(.system(size: 36))
                                    .foregroundColor(.orange)
                                Text(err)
                                    .foregroundColor(.secondary)
                                    .multilineTextAlignment(.center)
                                    .padding(.horizontal)
                                HStack(spacing: 12) {
                                    Button("Réessayer") { Task { await loadAvailableChallenges() } }
                                        .buttonStyle(.borderedProminent)
                                    Button("Fermer") { showChallengePicker = false }
                                }
                            }
                            .frame(maxWidth: .infinity, maxHeight: .infinity)
                        } else if availableChallenges.isEmpty {
                            VStack(spacing: 12) {
                                Image(systemName: "questionmark")
                                    .font(.system(size: 36))
                                    .foregroundColor(.secondary)
                                Text("Aucun challenge disponible")
                                    .foregroundColor(.secondary)
                                Button("Réessayer") { Task { await loadAvailableChallenges() } }
                                    .buttonStyle(.bordered)
                            }
                            .frame(maxWidth: .infinity, maxHeight: .infinity)
                        } else {
                            List(availableChallenges, id: \.id) { ch in
                                Button(action: {
                                    selectedChallengeId = ch.id
                                    showChallengePicker = false
                                    Task { await load(challengeId: ch.id) }
                                }) {
                                    HStack {
                                        Text(ch.title)
                                        if ch.id == (selectedChallengeId ?? activityId) {
                                            Image(systemName: "checkmark").foregroundColor(.accentColor)
                                        }
                                    }
                                }
                            }
                        }
                    }
                    .navigationTitle("Choisir un challenge")
                    .navigationBarTitleDisplayMode(.inline)
                    .toolbar {
                        ToolbarItem(placement: .cancellationAction) {
                            Button("Fermer") { showChallengePicker = false }
                        }
                    }
                    .onAppear {
                        if availableChallenges.isEmpty {
                            Task { await loadAvailableChallenges() }
                        }
                    }
                }
            }
        }
        .onAppear { Task { await load() } }
    }

    // Liste des challenges disponibles
    private func loadAvailableChallenges() async {
        isLoadingChallenges = true
        challengesError = nil
        defer { isLoadingChallenges = false }

        // Prefer token as GET param to ensure server accepts it regardless of header passthrough
        var listURLStr = "https://viendez.com/api/list-challenges.php"
        if let token = AuthService.shared.token, !token.isEmpty {
            listURLStr += "?token=\(token)"
        }
        guard let url = URL(string: listURLStr) else {
            challengesError = "URL invalide"
            return
        }
        var req = URLRequest(url: url)
        req.timeoutInterval = 10
        do {
            let (data, response) = try await URLSession.shared.data(for: req)
            #if DEBUG
            if let http = response as? HTTPURLResponse {
                print("[Challenge] list-challenges -> status:\(http.statusCode)")
                if let s = String(data: data, encoding: .utf8) { print("[Challenge] payload: \(s)") }
            }
            #endif
            if let http = response as? HTTPURLResponse, http.statusCode != 200 {
                // try to extract server error message
                if let obj = try? JSONSerialization.jsonObject(with: data) as? [String: Any], let err = obj["error"] as? String {
                    challengesError = "Erreur serveur: \(err)"
                } else {
                    challengesError = "Erreur serveur (code \(http.statusCode))"
                }
                // fallback on 404: attempt to discover challenges via activities
                if http.statusCode == 404 {
                    challengesError = "Endpoint introuvable (404) — recherche via activités..."
                    let found = await fetchChallengesFromActivities()
                    if !found.isEmpty {
                        availableChallenges = found
                        challengesError = nil
                    } else {
                        challengesError = "Aucun challenge trouvé après recherche"
                    }
                }
                return
            }

            if let json = try JSONSerialization.jsonObject(with: data) as? [[String: Any]] {
                let list = json.compactMap { ch -> (id: Int, title: String)? in
                    guard let id = ch["id"] as? Int, let title = ch["title"] as? String else { return nil }
                    return (id: id, title: title)
                }
                if !list.isEmpty {
                    availableChallenges = list

                    // if server only returned the current challenge, try to discover more
                    let currentId = selectedChallengeId ?? activityId
                    if list.count <= 1 && list.first?.id == currentId {
                        let found = await fetchChallengesFromActivities()
                        if !found.isEmpty {
                            // merge, keep unique ids and preserve order (server list first)
                            var merged = availableChallenges
                            for f in found where !merged.contains(where: { $0.id == f.id }) {
                                merged.append(f)
                            }
                            availableChallenges = merged
                            challengesError = nil
                        } else {
                            challengesError = "Aucun challenge trouvé après recherche"
                        }
                    }

                    return
                } else {
                    challengesError = "Aucun challenge trouvé"
                    // try fallback discovery
                    let found = await fetchChallengesFromActivities()
                    if !found.isEmpty {
                        availableChallenges = found
                        challengesError = nil
                    }
                    return
                }
            }

            // fallback: try parse object error
            if let obj = try? JSONSerialization.jsonObject(with: data) as? [String: Any], let err = obj["error"] as? String {
                challengesError = err
            } else {
                challengesError = "Réponse invalide"
            }
        } catch {
            challengesError = error.localizedDescription
            // try fallback discovery when network/server error occurs
            let found = await fetchChallengesFromActivities()
            if !found.isEmpty {
                availableChallenges = found
                challengesError = nil
            }
        }
    }

    // Fallback: query recent activities then call challenge-ranking.php for each to collect challenge titles
    // Returns discovered challenges and does not mutate state directly
    private func fetchChallengesFromActivities() async -> [(id: Int, title: String)] {
        guard let url = URL(string: "https://viendez.com/api/activities-list.php") else { return [] }
        var req = URLRequest(url: url)
        if let token = AuthService.shared.token {
            req.setValue("Bearer \(token)", forHTTPHeaderField: "Authorization")
        }
        req.timeoutInterval = 8
        do {
            let (data, response) = try await URLSession.shared.data(for: req)
            // accept either { "activities": [...] } or an array [...]
            var activitiesArray: [Any] = []
            if let http = response as? HTTPURLResponse, http.statusCode != 200 {
                return []
            }
            if let jsonObj = try? JSONSerialization.jsonObject(with: data) {
                if let dict = jsonObj as? [String: Any], let acts = dict["activities"] as? [Any] {
                    activitiesArray = acts
                } else if let arr = jsonObj as? [Any] {
                    activitiesArray = arr
                }
            }
            let recentIds = activitiesArray.compactMap { item -> Int? in
                if let d = item as? [String: Any], let id = d["id"] as? Int { return id }
                if let id = item as? Int { return id }
                return nil
            }.suffix(12)

            var found: [(id: Int, title: String)] = []
            for actId in recentIds {
                // Prefer token as GET param to ensure the endpoint accepts it
                var crURLStr = "\(baseURL)?activity_id=\(actId)"
                if let token = AuthService.shared.token, !token.isEmpty {
                    crURLStr += "&token=\(token)"
                }
                guard let crURL = URL(string: crURLStr) else { continue }
                var crReq = URLRequest(url: crURL)
                crReq.timeoutInterval = 6
                do {
                    let (cdata, response) = try await URLSession.shared.data(for: crReq)
                    #if DEBUG
                    if let http = response as? HTTPURLResponse {
                        print("[Challenge] challenge-ranking(\(actId)) -> status:\(http.statusCode)")
                        if let s = String(data: cdata, encoding: .utf8) { print("[Challenge] payload: \(s)") }
                    }
                    #endif
                    if let http = response as? HTTPURLResponse, http.statusCode == 200,
                       let cjson = try JSONSerialization.jsonObject(with: cdata) as? [String: Any],
                       let cid = cjson["challenge_id"] as? Int, let title = cjson["challenge_title"] as? String {
                        if !found.contains(where: { $0.id == cid }) {
                            found.append((id: cid, title: title))
                        }
                    }
                } catch { continue }
            }
            return found
        } catch {
            return []
        }
    }



    // MARK: - Header

    private var columnHeader: some View {
        HStack(spacing: 0) {
            Text("#")
                .frame(width: 36, alignment: .center)
            Text("Pseudo")
                .frame(maxWidth: .infinity, alignment: .leading)
            Text("Pts")
                .frame(width: 42, alignment: .trailing)
            Text("ITM")
                .frame(width: 32, alignment: .trailing)
            Text("Vic.")
                .frame(width: 36, alignment: .trailing)
            Text("Part.")
                .frame(width: 38, alignment: .trailing)
        }
        .font(.caption2.bold())
        .foregroundColor(.secondary)
        .padding(.horizontal, 16)
        .padding(.vertical, 7)
    }

    // MARK: - Row

    @ViewBuilder
    private func rowView(_ e: ChallengeEntry) -> some View {
        let isMe = e.pseudo.lowercased() == myPseudo.lowercased()
        HStack(spacing: 0) {
            // Rang
            rankBadge(e.rank)
                .frame(width: 36, alignment: .center)

            // Pseudo
            Text(e.pseudo)
                .font(isMe ? .body.bold() : .body)
                .foregroundColor(isMe ? .accentColor : .primary)
                .lineLimit(1)
                .frame(maxWidth: .infinity, alignment: .leading)

            // Points
            Text("\(e.points)")
                .font(.system(.subheadline, design: .rounded).bold())
                .foregroundColor(rankColor(e.rank))
                .frame(width: 42, alignment: .trailing)

            // TF
            Text(e.tf > 0 ? "\(e.tf)" : "-")
                .font(.system(size: 13))
                .foregroundColor(e.tf > 0 ? .blue : .secondary)
                .frame(width: 32, alignment: .trailing)

            // Victoires
            Text(e.nbVictoires > 0 ? "\(e.nbVictoires)" : "-")
                .font(.system(size: 13))
                .foregroundColor(e.nbVictoires > 0 ? .yellow : .secondary)
                .frame(width: 36, alignment: .trailing)

            // Participations
            Text("\(e.nbParticipations)")
                .font(.system(size: 13))
                .foregroundColor(.secondary)
                .frame(width: 38, alignment: .trailing)
        }
        .padding(.horizontal, 16)
        .padding(.vertical, 10)
        .background(isMe ? Color.accentColor.opacity(0.10) : Color.clear)
    }

    // MARK: - Rank badge

    @ViewBuilder
    private func rankBadge(_ rank: Int) -> some View {
        switch rank {
        case 1:
            Text("🥇").font(.title3)
        case 2:
            Text("🥈").font(.title3)
        case 3:
            Text("🥉").font(.title3)
        default:
            Text("\(rank)")
                .font(.system(.callout, design: .rounded).bold())
                .foregroundColor(.secondary)
        }
    }

    private func rankColor(_ rank: Int) -> Color {
        switch rank {
        case 1: return .yellow
        case 2: return Color(white: 0.7)
        case 3: return Color(red: 0.8, green: 0.5, blue: 0.2)
        default: return .primary
        }
    }

    // MARK: - Load

    private func load(challengeId: Int? = nil) async {
        isLoading = true
        errorMessage = nil
        defer { isLoading = false }

        guard let token = AuthService.shared.token else {
            errorMessage = "Non connecté"
            return
        }

        let challengeToLoad = challengeId ?? selectedChallengeId ?? activityId
        var urlStr = "\(baseURL)?activity_id=\(challengeToLoad)"
        guard let url = URL(string: urlStr) else {
            errorMessage = "URL invalide"
            return
        }
        _ = urlStr  // suppress warning

        var req = URLRequest(url: url)
        req.setValue("Bearer \(token)", forHTTPHeaderField: "Authorization")
        req.timeoutInterval = 10

        do {
            let (data, _) = try await URLSession.shared.data(for: req)
            guard let json = try JSONSerialization.jsonObject(with: data) as? [String: Any],
                  let success = json["success"] as? Bool, success,
                  let list = json["ranking"] as? [[String: Any]] else {
                errorMessage = (try? JSONSerialization.jsonObject(with: data) as? [String: Any])?["error"] as? String ?? "Réponse invalide"
                return
            }
            challengeTitle = json["challenge_title"] as? String ?? "Challenge"
            entries = list.compactMap { item in
                guard let rank   = item["rank"]       as? Int,
                      let pseudo = item["pseudo"]      as? String else { return nil }
                return ChallengeEntry(
                    id:               item["id_membre"]         as? Int    ?? 0,
                    rank:             rank,
                    pseudo:           pseudo,
                    nbParticipations: item["nb_participations"] as? Int    ?? 0,
                    tf:               item["tf"]                as? Int    ?? 0,
                    nbVictoires:      item["nb_victoires"]      as? Int    ?? 0,
                    cagnotte:         item["cagnotte"]          as? Double ?? 0,
                    points:           item["points"]            as? Int    ?? 0
                )
            }
            // Charger la liste des challenges disponibles si pas déjà fait
            if availableChallenges.isEmpty, let challenges = json["available_challenges"] as? [[String: Any]] {
                availableChallenges = challenges.compactMap { ch in
                    guard let id = ch["id"] as? Int, let title = ch["title"] as? String else { return nil }
                    return (id: id, title: title)
                }
            }
        } catch {
            errorMessage = "Erreur réseau"
        }
    }
}
