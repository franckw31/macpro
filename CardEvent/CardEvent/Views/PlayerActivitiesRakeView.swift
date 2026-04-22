import SwiftUI

struct ActivityRakeItem: Identifiable, Decodable {
    let aid: Int
    let title: String
    let date_depart: String
    let buyin: Double
    let rake: Double
    let gain: Double
    let popt: String

    var id: Int { aid }
}

struct PlayerActivitiesRakeView: View {
    let pseudo: String
    let memberId: Int?

    @State private var activities: [ActivityRakeItem] = []
    @State private var page = 1
    @State private var loading = false
    @State private var error: String? = nil

    var body: some View {
        List {
            if activities.isEmpty && loading {
                HStack { Spacer(); ProgressView(); Spacer() }
            } else if activities.isEmpty {
                Text("Aucune activité trouvée.")
            } else {
                ForEach(activities) { a in
                    VStack(alignment: .leading, spacing: 6) {
                        HStack {
                            Text(a.title).font(.headline)
                            Spacer()
                            Text(formatEur(a.rake)).foregroundColor(.red).fontWeight(.bold)
                        }
                        HStack {
                            Text(a.date_depart).font(.caption).foregroundColor(.secondary)
                            Spacer()
                            Text("Buyin: " + formatEur(a.buyin)).font(.caption)
                        }
                    }
                    .padding(.vertical, 8)
                }
                if !loading {
                    HStack { Spacer(); Button("Charger plus") { Task { await loadMore() } }; Spacer() }
                } else {
                    HStack { Spacer(); ProgressView(); Spacer() }
                }
            }
        }
        .navigationTitle("Activités — Rake")
        .onAppear { Task { await loadPage(1) } }
        .alert(item: Binding(get: { error.map { ErrWrapper(msg: $0) } }, set: { _ in error = nil })) { ew in
            Alert(title: Text("Erreur"), message: Text(ew.msg), dismissButton: .default(Text("OK")))
        }
    }

    private func loadMore() async {
        await loadPage(page + 1)
    }

    private func loadPage(_ p: Int) async {
        guard !loading else { return }
        loading = true
        defer { loading = false }

        let perPage = 25
        var urlStr = "https://viendez.com/api/player-activities-rake.php?page=\(p)&per_page=\(perPage)"
        if let mid = memberId, mid > 0 { urlStr += "&uid=\(mid)" } else { urlStr += "&pseudo=\(pseudo.addingPercentEncoding(withAllowedCharacters: .urlQueryAllowed) ?? pseudo)" }

        guard let url = URL(string: urlStr) else { error = "URL invalide"; return }

        var req = URLRequest(url: url)
        req.httpMethod = "GET"
        if let token = AuthService.shared.token { req.setValue("Bearer \(token)", forHTTPHeaderField: "Authorization") }

        do {
            let (data, resp) = try await URLSession.shared.data(for: req)
            guard let http = resp as? HTTPURLResponse, http.statusCode == 200 else { error = "Réponse serveur incorrecte"; return }
            let root = try JSONDecoder().decode(ActivitiesRakeResponse.self, from: data)
            if p == 1 { activities = root.rake_contrib } else { activities.append(contentsOf: root.rake_contrib) }
            page = p
        } catch {
            error = error.localizedDescription
        }
    }
}

private struct ActivitiesRakeResponse: Decodable {
    let success: Bool
    let uid: Int?
    let page: Int?
    let per_page: Int?
    let rake_total: Int?
    let rake_contrib: [ActivityRakeItem]
    let non_organizer_total: Int?
    let non_organizer: [ActivityRakeItem]?
}

private struct ErrWrapper: Identifiable {
    let id = UUID()
    let msg: String
}

// Helper formatting used across app
fileprivate func formatEur(_ v: Double) -> String {
    let intVal = Int(round(v))
    let nf = NumberFormatter()
    nf.numberStyle = .decimal
    nf.groupingSeparator = " "
    nf.locale = Locale(identifier: "fr_FR")
    return (nf.string(from: NSNumber(value: intVal)) ?? "0") + " €"
}
