import SwiftUI
import UIKit

// MARK: - PlayerStats Model

private struct PlayerStats {
    let photoUrl: String
    let nbParties: Int
    let totalGains: Double
    let nbGains: Int
    let totalBuyins: Double
    let netResult: Double
    let nbVictoires: Int
    let nbPodiums: Int
    let totalRecaves: Int
    let meilleurGain: Double
    let tauxVictoire: Double
    let tauxPodium: Double
}

// TicketsListView is provided as a separate file `TicketsListView.swift`.


// removed Safari wrapper — Tickets are shown in-app via TicketsListView

// MARK: - PlayerProfileView

struct PlayerProfileView: View {
    let pseudo: String
    let participant: Participant?
    let activityTitle: String
    let activityId: Int
    @Environment(\.dismiss) private var dismiss

    @State private var stats: PlayerStats? = nil
    @State private var statsLoading = false
    @State private var statsError: String? = nil
    @State private var memberId: Int? = nil
    @State private var memberTickets: Int? = nil
    @State private var showMemberTickets: Bool = false
    // Image picker / upload
    @State private var showingImagePicker = false
    @State private var inputImage: UIImage? = nil
    @State private var isUploading = false
    @State private var uploadMessage: String? = nil

    var body: some View {
        NavigationView {
            ScrollView {
                VStack(spacing: 20) {

                    // Avatar
                    ZStack {
                        if let s = stats, !s.photoUrl.isEmpty, let url = URL(string: s.photoUrl) {
                            AsyncImage(url: url) { phase in
                                switch phase {
                                case .success(let image):
                                    image
                                        .resizable()
                                        .scaledToFill()
                                        .frame(width: 90, height: 90)
                                        .clipShape(Circle())
                                case .failure, .empty:
                                    initialsCircle
                                @unknown default:
                                    initialsCircle
                                }
                            }
                        } else {
                            initialsCircle
                        }
                        // Edit overlay when it's the current user
                        if pseudo == AuthService.shared.pseudo {
                            VStack {
                                Spacer()
                                HStack {
                                    Spacer()
                                    Button(action: { showingImagePicker = true }) {
                                        Image(systemName: "pencil.circle.fill")
                                            .resizable()
                                            .frame(width: 32, height: 32)
                                            .foregroundColor(.blue)
                                            .background(Circle().fill(Color.white).frame(width: 36, height: 36))
                                    }
                                    .padding(.trailing, 4)
                                }
                            }
                        }
                    }
                    .sheet(isPresented: $showingImagePicker, onDismiss: {
                        if let _ = inputImage {
                            Task { await uploadSelectedImage() }
                        }
                    }) {
                        ImagePicker(image: $inputImage)
                    }
                    .overlay(Group {
                        if isUploading {
                            ProgressView().padding(8).background(Color(.systemBackground)).cornerRadius(8)
                        }
                    })
                    .padding(.top, 20)

                    Text(pseudo)
                        .font(.title2.bold())

                    if let p = participant {
                        // Statut badge (hide the generic "Inscrit" label under the pseudo)
                        let statutResolved = statutInfo(p.statut)
                        if statutResolved.0 != "Inscrit" {
                            statutBadge(p.statut)
                        }

                        // Stats de la partie en cours
                        GroupBox(activityTitle.isEmpty ? "Partie en cours" : activityTitle) {
                            VStack(spacing: 0) {
                                statRow(label: "Inscription", value: p.dateInscription.isEmpty ? "—" : p.dateInscription)
                                Divider()
                                statRow(label: "Bonus inscription", value: p.bonus1 > 0 ? "+\(p.bonus1)" : "—", valueColor: p.bonus1 > 0 ? .blue : .secondary)
                                if p.gain >= 1 {
                                    Divider()
                                    statRow(label: "Recave(s)", value: p.recave > 0 ? "\(p.recave)" : "—", valueColor: p.recave > 0 ? .orange : .secondary)
                                    Divider()
                                    statRow(label: "Bounty", value: p.bounty > 0 ? "\(p.bounty)" : "—", valueColor: p.bounty > 0 ? Color(red: 0.6, green: 0.2, blue: 0.8) : .secondary)
                                    if p.classement > 0 {
                                        Divider()
                                        statRow(label: "Classement", value: "#\(p.classement)",
                                                valueColor: p.classement == 1 ? .yellow : p.classement == 2 ? Color(white: 0.6) : p.classement == 3 ? Color(red: 0.8, green: 0.5, blue: 0.2) : .primary)
                                        Divider()
                                        statRow(label: "Gains", value: "\(p.gain)€",
                                                valueColor: p.gain > 0 ? .green : p.gain < 0 ? .red : .secondary)
                                    }
                                }
                            }
                        }
                        .padding(.horizontal)
                    } else {
                        Text("Pas encore inscrit à cette activité")
                            .font(.subheadline)
                            .foregroundColor(.secondary)
                    }

                    // ── Rang Challenge + Notes (toujours visibles) ─────────
                    GroupBox {
                        VStack(spacing: 0) {
                            let challengeRank = participant?.challengeRank ?? 0
                            NavigationLink {
                                ChallengeRankingView(
                                    activityId: activityId,
                                    myPseudo: pseudo
                                )
                            } label: {
                                HStack {
                                    Text("Rang Challenge")
                                        .font(.subheadline)
                                        .foregroundColor(.secondary)
                                    Spacer()
                                    if challengeRank > 0 {
                                        Text("#\(challengeRank)")
                                            .font(.subheadline.bold())
                                            .foregroundColor(challengeRank == 1 ? .yellow : challengeRank <= 3 ? .orange : .primary)
                                    }
                                    Text("Visualiser")
                                        .font(.subheadline)
                                        .foregroundColor(.blue)
                                        .underline()
                                }
                                .padding(.vertical, 8)
                                .padding(.horizontal, 4)
                            }
                            .buttonStyle(.plain)

                            Divider()

                            // Vos Tickets de Tombola
                            HStack {
                                Text("Vos Tickets de Tombola")
                                    .font(.subheadline)
                                    .foregroundColor(.secondary)
                                Spacer()
                                Text(memberTickets != nil ? "\(memberTickets!)" : "—")
                                    .font(.subheadline.bold())
                                    .foregroundColor(.primary)
                                Button("Voir") {
                                    if let mid = memberId {
                                        showMemberTickets = true
                                    }
                                }
                                .font(.subheadline)
                                .disabled(memberId == nil)
                                .foregroundColor(memberId == nil ? .secondary : .blue)
                            }
                            .padding(.vertical, 8)
                            .padding(.horizontal, 4)

                            Divider()

                            NavigationLink {
                                PlayerTrakView(pseudo: pseudo, activityId: activityId)
                            } label: {
                                HStack {
                                    Text("Notes (Traker) 📝")
                                        .font(.subheadline)
                                        .foregroundColor(.secondary)
                                    Spacer()
                                    Text("Voir")
                                        .font(.subheadline)
                                        .foregroundColor(.blue)
                                        .underline()
                                }
                                .padding(.vertical, 8)
                                .padding(.horizontal, 4)
                            }
                            .buttonStyle(.plain)
                        }
                    }
                    .padding(.horizontal)

                    // ── Statistiques globales ──────────────────────────────
                    statsSection
                        .padding(.horizontal)

                    // ── Bouton Déconnexion (si c'est nous) ─────────────────
                    if pseudo == AuthService.shared.pseudo {
                        Button(role: .destructive) {
                            Task {
                                await AuthService.shared.logout()
                                dismiss()
                            }
                        } label: {
                            HStack {
                                Image(systemName: "rectangle.portrait.and.arrow.right")
                                Text("Se déconnecter")
                                    .fontWeight(.bold)
                            }
                            .frame(maxWidth: .infinity)
                            .padding()
                            .background(Color.red.opacity(0.15))
                            .foregroundColor(.red)
                            .cornerRadius(10)
                        }
                        .padding(.horizontal)
                        .padding(.top, 10)
                    }

                    Spacer(minLength: 20)
                }
                .frame(maxWidth: .infinity)
            }
            .navigationBarTitleDisplayMode(.inline)
            .toolbar {
                ToolbarItem(placement: .navigationBarTrailing) {
                    Button("Fermer") { dismiss() }
                }
            }
            .onAppear {
                Task { await loadStats() }
            }
            .sheet(isPresented: $showMemberTickets) {
                if let mid = memberId {
                    TicketsListView(memberId: mid, pseudo: pseudo)
                }
            }
        }
    }

    // MARK: - Stats Section

    @ViewBuilder
    private var statsSection: some View {
        GroupBox {
            if statsLoading {
                HStack {
                    Spacer()
                    ProgressView().padding(.vertical, 12)
                    Spacer()
                }
            } else if let s = stats {
                VStack(spacing: 0) {
                    // Ligne récapitulative buy-ins / gains (identique à quickview.php)
                    HStack(spacing: 12) {
                        linkedStatTile(
                            title: "Buy-ins",
                            value: formatEur(s.totalBuyins),
                            sub: "\(s.nbParties) partie" + (s.nbParties > 1 ? "s" : ""),
                            color: .secondary,
                            type: "buyins"
                        )
                        Divider().frame(height: 62)
                        linkedStatTile(
                            title: "Gains",
                            value: formatEur(s.totalGains),
                            sub: "\(s.nbGains) fois",
                            color: s.totalGains >= 0 ? .green : .red,
                            type: "gains"
                        )
                        Divider().frame(height: 62)
                        statTile(
                            title: "Net",
                            value: (s.netResult >= 0 ? "+" : "") + formatEur(s.netResult),
                            sub: s.netResult >= 0 ? "✓" : "✗",
                            color: s.netResult >= 0 ? .green : .red
                        )
                    }
                    .padding(.vertical, 10)

                    Divider()

                    // Palmarès
                    HStack(spacing: 12) {
                        linkedStatTile(
                            title: "Victoires",
                            value: "\(s.nbVictoires)",
                            sub: s.tauxVictoire > 0 ? "\(s.tauxVictoire) %" : "—",
                            color: .yellow,
                            type: "victoires"
                        )
                        Divider().frame(height: 62)
                        linkedStatTile(
                            title: "Podiums",
                            value: "\(s.nbPodiums)",
                            sub: s.tauxPodium > 0 ? "\(s.tauxPodium) %" : "—",
                            color: Color(red: 0.8, green: 0.5, blue: 0.2),
                            type: "podiums"
                        )
                        Divider().frame(height: 62)
                        linkedStatTile(
                            title: "Recaves",
                            value: "\(s.totalRecaves)",
                            sub: s.nbParties > 0 ? String(format: "%.1f/partie", Double(s.totalRecaves) / Double(s.nbParties)) : "—",
                            color: .orange,
                            type: "recaves"
                        )
                    }
                    .padding(.vertical, 10)

                    if s.meilleurGain > 0 {
                        Divider()
                        NavigationLink {
                            PlayerStatsDetailView(pseudo: pseudo, type: "meilleur_gain", navTitle: "Meilleur gain")
                        } label: {
                            HStack {
                                Text("Meilleur gain")
                                    .font(.subheadline)
                                    .foregroundColor(.secondary)
                                Spacer()
                                Text(formatEur(s.meilleurGain))
                                    .font(.subheadline.bold())
                                    .foregroundColor(.green)
                                Text("Détail")
                                    .font(.subheadline)
                                    .foregroundColor(.blue)
                                    .underline()
                            }
                            .padding(.vertical, 8)
                            .padding(.horizontal, 4)
                        }
                        .buttonStyle(.plain)
                    }
                }
            } else if let err = statsError {
                Text(err)
                    .font(.caption)
                    .foregroundColor(.secondary)
                    .padding(.vertical, 8)
            }
        } label: {
            Label("Statistiques", systemImage: "chart.line.uptrend.xyaxis")
                .font(.headline)
        }
    }

    private func statTile(title: String, value: String, sub: String, color: Color) -> some View {
        VStack(spacing: 3) {
            Text(title)
                .font(.caption2)
                .foregroundColor(.secondary)
                .textCase(.uppercase)
                .tracking(1)
            Text(value)
                .font(.system(.subheadline, design: .rounded).bold())
                .foregroundColor(color)
                .lineLimit(1)
                .minimumScaleFactor(0.7)
            Text(sub)
                .font(.caption2)
                .foregroundColor(.secondary)
        }
        .frame(maxWidth: .infinity)
    }

    private func linkedStatTile(title: String, value: String, sub: String, color: Color, type: String) -> some View {
        NavigationLink {
            PlayerStatsDetailView(pseudo: pseudo, type: type, navTitle: title)
        } label: {
            VStack(spacing: 3) {
                Text(title)
                    .font(.caption2)
                    .foregroundColor(.secondary)
                    .textCase(.uppercase)
                    .tracking(1)
                Text(value)
                    .font(.system(.subheadline, design: .rounded).bold())
                    .foregroundColor(color)
                    .lineLimit(1)
                    .minimumScaleFactor(0.7)
                Text(sub)
                    .font(.caption2)
                    .foregroundColor(.secondary)
                Text("Détail")
                    .font(.caption2)
                    .foregroundColor(.blue)
                    .underline()
            }
            .frame(maxWidth: .infinity)
        }
        .buttonStyle(.plain)
    }

    private func formatEur(_ v: Double) -> String {
        let absV = abs(v)
        let formatted: String
        if absV >= 1000 {
            formatted = String(format: "%.0f €", v)
        } else {
            formatted = String(format: "%.0f €", v)
        }
        return formatted
    }

    // MARK: - Load Stats

    private func loadStats() async {
        statsLoading = true
        defer { statsLoading = false }
        guard let encodedPseudo = pseudo.addingPercentEncoding(withAllowedCharacters: .urlQueryAllowed),
              let url = URL(string: "https://viendez.com/api/player-stats.php?pseudo=\(encodedPseudo)") else {
            statsError = "URL invalide"
            return
        }
        do {
            let (data, _) = try await URLSession.shared.data(from: url)
                if let json = try JSONSerialization.jsonObject(with: data) as? [String: Any],
                    let success = json["success"] as? Bool, success {
                stats = PlayerStats(
                    photoUrl:     json["photo_url"]    as? String ?? "",
                    nbParties:    json["nb_parties"]    as? Int    ?? 0,
                    totalGains:   (json["total_gains"]   as? Double) ?? Double(json["total_gains"]   as? Int ?? 0),
                    nbGains:      json["nb_gains"]      as? Int    ?? 0,
                    totalBuyins:  (json["total_buyins"]  as? Double) ?? Double(json["total_buyins"]  as? Int ?? 0),
                    netResult:    (json["net_result"]    as? Double) ?? Double(json["net_result"]    as? Int ?? 0),
                    nbVictoires:  json["nb_victoires"]  as? Int    ?? 0,
                    nbPodiums:    json["nb_podiums"]    as? Int    ?? 0,
                    totalRecaves: json["total_recaves"] as? Int    ?? 0,
                    meilleurGain: (json["meilleur_gain"] as? Double) ?? Double(json["meilleur_gain"] as? Int ?? 0),
                    tauxVictoire: (json["taux_victoire"] as? Double) ?? 0,
                    tauxPodium:   (json["taux_podium"]   as? Double) ?? 0
                )
                // optional: member id and tickets if returned by the API
                if let mid = json["member_id"] as? Int { memberId = mid }
                else if let mid2 = json["id_membre"] as? Int { memberId = mid2 }
                if let t = json["tickets"] as? Int { memberTickets = t }
            } else {
                statsError = "Aucune donnée"
            }
        } catch {
            statsError = "Chargement impossible"
        }
    }

    // MARK: - Image upload

    private func uploadSelectedImage() async {
        guard let img = inputImage else { return }
        guard let data = img.jpegData(compressionQuality: 0.7) else { return }

        await MainActor.run { isUploading = true; uploadMessage = nil }
        defer { Task { await MainActor.run { isUploading = false } } }

        let boundary = "Boundary-\(UUID().uuidString)"
        guard let url = URL(string: "https://viendez.com/api/upload-avatar.php") else { return }
        var req = URLRequest(url: url)
        req.httpMethod = "POST"
        req.setValue("multipart/form-data; boundary=\(boundary)", forHTTPHeaderField: "Content-Type")
        if let token = AuthService.shared.token { req.setValue("Bearer \(token)", forHTTPHeaderField: "Authorization") }

        var body = Data()
        body.append("--\(boundary)\r\n".data(using: .utf8)!)
        body.append("Content-Disposition: form-data; name=\"file\"; filename=\"avatar.jpg\"\r\n".data(using: .utf8)!)
        body.append("Content-Type: image/jpeg\r\n\r\n".data(using: .utf8)!)
        body.append(data)
        body.append("\r\n".data(using: .utf8)!)
        body.append("--\(boundary)--\r\n".data(using: .utf8)!)

        req.httpBody = body

        do {
            let (d, _) = try await URLSession.shared.data(for: req)
            if let json = try JSONSerialization.jsonObject(with: d) as? [String: Any], let success = json["success"] as? Bool, success {
                await MainActor.run {
                    uploadMessage = "Avatar mis à jour"
                }
                await loadStats()
            } else {
                let err = (try JSONSerialization.jsonObject(with: d) as? [String: Any])? ["error"] as? String ?? "Erreur"
                await MainActor.run { uploadMessage = err }
            }
        } catch {
            await MainActor.run { uploadMessage = "Erreur réseau" }
        }
    }

    private var initialsCircle: some View {
        ZStack {
            Circle()
                .fill(Color.accentColor.opacity(0.15))
                .frame(width: 90, height: 90)
            Text(pseudo.prefix(1).uppercased())
                .font(.system(size: 40, weight: .bold, design: .rounded))
                .foregroundColor(.accentColor)
        }
    }

    private func statutBadge(_ statut: String) -> some View {
        let (label, color) = statutInfo(statut)
        return Text(label)
            .font(.caption.bold())
            .foregroundColor(.white)
            .padding(.horizontal, 12)
            .padding(.vertical, 5)
            .background(color)
            .cornerRadius(8)
    }

    private func statutInfo(_ statut: String) -> (String, Color) {
        switch statut {
        case "Présent", "Present":              return ("Présent", .green)
        case "Inscrit":                          return ("Inscrit", .blue)
        case "Confirmé", "Confirme":             return ("Confirmé", Color(red: 0, green: 0.7, blue: 0.8))
        case "Réservation", "Reservation":       return ("Inscrit", .orange)
        case "Option":                           return ("Option", Color(red: 0.8, green: 0.6, blue: 0))
        case "Eliminé", "Elimine":               return ("Éliminé", .red)
        default:                                  return (statut, .gray)
        }
    }

    private func statRow(label: String, value: String, valueColor: Color = .primary) -> some View {
        HStack {
            Text(label)
                .font(.subheadline)
                .foregroundColor(.secondary)
            Spacer()
            Text(value)
                .font(.subheadline.bold())
                .foregroundColor(valueColor)
        }
        .padding(.vertical, 8)
        .padding(.horizontal, 4)
    }
}
