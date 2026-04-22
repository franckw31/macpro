import SwiftUI
#if canImport(UIKit)
import UIKit
#endif

// MARK: - PlayerStats Model

private struct PlayerStats {
    let photoUrl: String
    let nbParties: Int
    let nbPartiesWithGain: Int
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
#if canImport(UIKit)
    @State private var showingImagePicker = false
    @State private var inputImage: UIImage? = nil
    @State private var isUploading = false
    @State private var uploadMessage: String? = nil
#else
    @State private var showingImagePicker = false
    @State private var isUploading = false
    @State private var uploadMessage: String? = nil
#endif

    var body: some View {
        NavigationView {
            ScrollView {
                VStack(spacing: 20) {

                    // Avatar
                    ZStack(alignment: .center) {
                        if let s = stats, !s.photoUrl.isEmpty, let url = URL(string: s.photoUrl) {
                            AsyncImage(url: url) { phase in
                                switch phase {
                                case .success(let image):
                                    image
                                        .resizable()
                                        .scaledToFill()
                                        .frame(width: 150, height: 150)
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

                        // Edit overlay when it's the current user: black camera circle overlapping lower-right
                        if pseudo == AuthService.shared.pseudo {
                            Button(action: { showingImagePicker = true }) {
                                ZStack {
                                    Circle()
                                        .fill(Color.black)
                                        .frame(width: 36, height: 36)
                                        .overlay(Circle().stroke(Color(.systemBackground).opacity(0.15), lineWidth: 2))
                                    Image(systemName: "camera")
                                        .foregroundColor(.white)
                                        .font(.system(size: 16, weight: .semibold))
                                        .symbolRenderingMode(.hierarchical)
                                }
                                .shadow(color: Color.black.opacity(0.25), radius: 4, x: 0, y: 2)
                            }
                            .buttonStyle(PlainButtonStyle())
                            .offset(x: 56, y: 52)
                        }
                    }
                    .sheet(isPresented: $showingImagePicker, onDismiss: {
                        #if canImport(UIKit)
                        if let _ = inputImage {
                            Task { await uploadSelectedImage() }
                        }
                        #endif
                    }) {
                                             #if canImport(UIKit)
                        ImagePicker(image: $inputImage)
                        #else
                        EmptyView()
                        #endif
                    }
                    .overlay(Group {
                        if isUploading {
                            #if canImport(UIKit)
                            ProgressView().padding(8).background(Color(UIColor.systemBackground)).cornerRadius(8)
                            #else
                            ProgressView().padding(8).background(Color.clear).cornerRadius(8)
                            #endif
                        }
                    })
                    .padding(.top, -28)

                    Text(pseudo)
                        .font(.system(size: 28, weight: .bold))
                        .foregroundColor(Color(red: 0.086, green: 0.639, blue: 0.29))

                    

                    if let p = participant {
                        // Statut badge (hide the generic "Inscrit" label under the pseudo)
                        let statutResolved = statutInfo(p.statut)
                        if statutResolved.0 != "Inscrit" {
                            statutBadge(p.statut)
                        }

                        // Stats de la partie en cours — single-line Inscription + bonus
                        GroupBox(activityTitle.isEmpty ? "Partie en cours" : activityTitle) {
                            HStack {
                                Text("Inscription")
                                    .font(.subheadline)
                                    .foregroundColor(.secondary)
                                Spacer()
                                HStack(spacing: 8) {
                                    Text(p.dateInscription.isEmpty ? "—" : p.dateInscription)
                                        .font(.subheadline.bold())
                                        .foregroundColor(.primary)
                                    if p.bonus1 > 0 {
                                        Text("(+\(p.bonus1))")
                                            .font(.subheadline.bold())
                                            .foregroundColor(.yellow)
                                    }
                                }
                            }
                            .padding(.vertical, 10)
                            .padding(.horizontal, 4)
                        }
                        .padding(.horizontal)
                        .background(
                            RoundedRectangle(cornerRadius: 14, style: .continuous)
                                .fill(Color(red: 0.06, green: 0.09, blue: 0.11))
                                .overlay(RoundedRectangle(cornerRadius: 14, style: .continuous).stroke(Color.white.opacity(0.03), lineWidth: 1))
                        )
                        .shadow(color: Color.black.opacity(0.35), radius: 6, x: 0, y: 4)
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
                                    showMemberTickets = (memberId != nil)
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
                                    Text("Notes (Traker)")
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
                            sub: "\(s.nbParties) parties",
                            color: Color(red: 0.6039, green: 0.6509, blue: 0.6941), // #9aa6b1
                            type: "buyins"
                        )
                        Divider().frame(height: 62)
                        linkedStatTile(
                            title: "Gains",
                            value: formatEur(s.totalGains),
                            sub: "\(s.nbGains) Gains",
                            color: s.totalGains >= 0 ? Color(red: 0.0863, green: 0.6392, blue: 0.2902) : Color(red: 1, green: 0.302, blue: 0.302), // green / red matching panel
                            type: "gains"
                        )
                        Divider().frame(height: 62)
                        statTile(
                            title: "Net",
                            value: (s.netResult >= 0 ? "+" : "") + formatEur(s.netResult),
                            sub: "BRUT",
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
                            sub: {
                                let denom = s.nbPartiesWithGain > 0 ? s.nbPartiesWithGain : s.nbParties
                                return denom > 0 ? String(format: "%.1f%%", (Double(s.nbVictoires) / Double(denom)) * 100.0) : "—"
                            }(),
                            color: Color(red: 1.0, green: 0.8196, blue: 0.0), // #ffd100
                            type: "victoires"
                        )
                        Divider().frame(height: 62)
                        linkedStatTile(
                            title: "ITM",
                            value: "\(s.nbGains)",
                            sub: {
                                let denom = s.nbPartiesWithGain > 0 ? s.nbPartiesWithGain : s.nbParties
                                return denom > 0 ? String(format: "%.1f%%", (Double(s.nbGains) / Double(denom)) * 100.0) : "—"
                            }(),
                            color: Color(red: 1.0, green: 0.6157, blue: 0.2314), // #ff9d3b
                            type: "gains"
                        )
                        Divider().frame(height: 62)
                        linkedStatTile(
                            title: "Recaves",
                            value: "\(s.totalRecaves)",
                            sub: s.nbParties > 0 ? String(format: "%.1f%%", (Double(s.totalRecaves) / Double(s.nbParties)) * 100.0) : "—",
                            color: Color(red: 0.0314, green: 0.6902, blue: 1.0), // #08b0ff
                            type: "recaves"
                        )
                    }
                    .padding(.vertical, 10)

                    if s.meilleurGain > 0 {
                        Divider()
                        HStack {
                            Text("Meilleur gain")
                                .font(.subheadline)
                                .foregroundColor(.secondary)
                            Spacer()
                            Text(formatEur(s.meilleurGain))
                                .font(.subheadline.bold())
                                .foregroundColor(.green)
                        }
                        .padding(.vertical, 8)
                        .padding(.horizontal, 4)
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
        VStack(spacing: 3) {
            Text(title)
                .font(.caption2)
                .foregroundColor(.secondary)
                .textCase(.uppercase)
                .tracking(1)

            NavigationLink(destination: PlayerStatsDetailView(pseudo: pseudo, type: type, navTitle: title)) {
                Text(value)
                    .font(.system(.subheadline, design: .rounded).bold())
                    .foregroundColor(color)
                    .underline(true, color: Color(red: 0.0314, green: 0.6902, blue: 1.0)) // blue underline (#08b0ff)
                    .lineLimit(1)
                    .minimumScaleFactor(0.7)
            }
            .buttonStyle(.plain)

            Text(sub)
                .font(.caption2)
                .foregroundColor(.secondary)
        }
        .frame(maxWidth: .infinity)
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
                    nbPartiesWithGain: json["nb_parties_with_gain"] as? Int ?? 0,
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

#if canImport(UIKit)
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
        // include token as form field (fallback for servers that don't receive Authorization header)
        if let token = AuthService.shared.token {
            body.append("--\(boundary)\r\n".data(using: .utf8)!)
            body.append("Content-Disposition: form-data; name=\"token\"\r\n\r\n".data(using: .utf8)!)
            body.append("\(token)\r\n".data(using: .utf8)!)
        }

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
#endif

    private var initialsCircle: some View {
        ZStack {
            Circle()
                .fill(Color.accentColor.opacity(0.12))
                .frame(width: 150, height: 150)
            Text(pseudo.prefix(1).uppercased())
                .font(.system(size: 64, weight: .bold, design: .rounded))
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
