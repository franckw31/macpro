import SwiftUI

struct PlayerStatsDetailView: View {
    let pseudo: String
    let type: String       // buyins | gains | victoires | podiums | recaves | meilleur_gain
    let navTitle: String

    @State private var items: [[String: Any]] = []
    @State private var loading = false
    @State private var errorMsg: String? = nil

    var body: some View {
        Group {
            if loading {
                ProgressView("Chargement…")
                    .frame(maxWidth: .infinity, maxHeight: .infinity)
            } else if let err = errorMsg {
                VStack(spacing: 12) {
                    Image(systemName: "exclamationmark.triangle")
                        .font(.largeTitle)
                        .foregroundColor(.secondary)
                    Text(err)
                        .foregroundColor(.secondary)
                }
                .frame(maxWidth: .infinity, maxHeight: .infinity)
            } else if items.isEmpty {
                VStack(spacing: 12) {
                    Image(systemName: "tray")
                        .font(.largeTitle)
                        .foregroundColor(.secondary)
                    Text("Aucun résultat")
                        .foregroundColor(.secondary)
                }
                .frame(maxWidth: .infinity, maxHeight: .infinity)
            } else {
                List {
                    ForEach(Array(items.enumerated()), id: \.offset) { _, item in
                        rowView(item)
                    }
                }
                .listStyle(.insetGrouped)
            }
        }
        .navigationTitle(navTitle)
        .navigationBarTitleDisplayMode(.inline)
        .onAppear { Task { await load() } }
    }

    // MARK: - Row

    @ViewBuilder
    private func rowView(_ item: [String: Any]) -> some View {
        let date  = item["date_partie"] as? String ?? ""
        let titre = item["titre"]       as? String ?? ""

        switch type {
        case "buyins":
            let total    = item["total_buyin"] as? Int    ?? 0
            let recaves  = item["recaves"]     as? Int    ?? 0
            let classe   = item["classement"]  as? Int    ?? 0
            let nbJoueurs = item["nb_joueurs"] as? Int    ?? 0
            let gain     = (item["gain"] as? Double) ?? Double(item["gain"] as? Int ?? 0)
            HStack(alignment: .top) {
                VStack(alignment: .leading, spacing: 2) {
                    Text(titre).font(.subheadline).lineLimit(1)
                    Text(date).font(.caption).foregroundColor(.secondary)
                }
                Spacer()
                VStack(alignment: .trailing, spacing: 3) {
                    Text("-\(total) €")
                        .font(.subheadline.bold())
                        .foregroundColor(.primary)
                    HStack(spacing: 6) {
                        if recaves > 0 {
                            Text("\(recaves) recave\(recaves > 1 ? "s" : "")")
                                .font(.caption2)
                                .foregroundColor(.orange)
                        }
                        if nbJoueurs > 0 {
                            Text("\(nbJoueurs) joueurs")
                                .font(.caption2)
                                .foregroundColor(.secondary)
                        }
                    }
                    HStack(spacing: 6) {
                        if classe > 0 {
                            Text("#\(classe) / \(nbJoueurs > 0 ? "\(nbJoueurs)" : "?")")
                                .font(.caption2.bold())
                                .foregroundColor(classColor(classe))
                        }
                        if gain > 0 {
                            Text(String(format: "+%.0f €", gain))
                                .font(.caption2.bold())
                                .foregroundColor(.green)
                        }
                    }
                }
            }

        case "gains", "meilleur_gain":
            let gain   = (item["gain"]       as? Double) ?? Double(item["gain"] as? Int ?? 0)
            let classe = item["classement"]  as? Int    ?? 0
            HStack {
                VStack(alignment: .leading, spacing: 2) {
                    Text(titre).font(.subheadline).lineLimit(1)
                    Text(date).font(.caption).foregroundColor(.secondary)
                }
                Spacer()
                VStack(alignment: .trailing, spacing: 2) {
                    Text(String(format: "+%.0f €", gain))
                        .font(.subheadline.bold())
                        .foregroundColor(.green)
                    if classe > 0 {
                        Text("#\(classe)")
                            .font(.caption2.bold())
                            .foregroundColor(classColor(classe))
                    }
                }
            }

        case "victoires", "podiums":
            let gain   = (item["gain"]      as? Double) ?? Double(item["gain"] as? Int ?? 0)
            let classe = item["classement"] as? Int    ?? 0
            HStack {
                VStack(alignment: .leading, spacing: 2) {
                    Text(titre).font(.subheadline).lineLimit(1)
                    Text(date).font(.caption).foregroundColor(.secondary)
                }
                Spacer()
                VStack(alignment: .trailing, spacing: 2) {
                    if classe > 0 {
                        Text(podiumEmoji(classe) + " #\(classe)")
                            .font(.subheadline.bold())
                            .foregroundColor(classColor(classe))
                    }
                    if gain > 0 {
                        Text(String(format: "+%.0f €", gain))
                            .font(.caption)
                            .foregroundColor(.green)
                    }
                }
            }

        case "recaves":
            let recaves = item["recaves"]    as? Int    ?? 0
            let buyin   = item["buyin"]      as? Int    ?? 0
            let gain    = (item["gain"]      as? Double) ?? Double(item["gain"] as? Int ?? 0)
            let classe  = item["classement"] as? Int    ?? 0
            HStack {
                VStack(alignment: .leading, spacing: 2) {
                    Text(titre).font(.subheadline).lineLimit(1)
                    Text(date).font(.caption).foregroundColor(.secondary)
                }
                Spacer()
                VStack(alignment: .trailing, spacing: 2) {
                    Text("\(recaves) recave\(recaves > 1 ? "s" : "")")
                        .font(.subheadline.bold())
                        .foregroundColor(.orange)
                    HStack(spacing: 6) {
                        Text("\(recaves * buyin) €")
                            .font(.caption2)
                            .foregroundColor(.secondary)
                        if classe > 0 {
                            Text("#\(classe)")
                                .font(.caption2.bold())
                                .foregroundColor(classColor(classe))
                        } else if gain > 0 {
                            Text(String(format: "+%.0f €", gain))
                                .font(.caption2)
                                .foregroundColor(.green)
                        }
                    }
                }
            }

        default:
            EmptyView()
        }
    }

    // MARK: - Helpers

    private func classColor(_ c: Int) -> Color {
        switch c {
        case 1: return .yellow
        case 2: return Color(white: 0.55)
        case 3: return Color(red: 0.8, green: 0.5, blue: 0.2)
        default: return .primary
        }
    }

    private func podiumEmoji(_ c: Int) -> String {
        switch c { case 1: return "🥇"; case 2: return "🥈"; case 3: return "🥉"; default: return "" }
    }

    // MARK: - Load

    private func load() async {
        loading = true
        defer { loading = false }
        guard let encodedPseudo = pseudo.addingPercentEncoding(withAllowedCharacters: .urlQueryAllowed),
              let url = URL(string: "https://viendez.com/api/player-stats-detail.php?pseudo=\(encodedPseudo)&type=\(type)") else {
            errorMsg = "URL invalide"; return
        }
        do {
            let (data, _) = try await URLSession.shared.data(from: url)
            if let json = try JSONSerialization.jsonObject(with: data) as? [String: Any],
               let success = json["success"] as? Bool, success,
               let rawItems = json["items"] as? [[String: Any]] {
                items = rawItems
            } else {
                errorMsg = "Aucune donnée"
            }
        } catch {
            errorMsg = "Chargement impossible"
        }
    }
}
