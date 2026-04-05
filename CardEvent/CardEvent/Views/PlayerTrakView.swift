import SwiftUI

// MARK: - Model

private struct TrakNote: Identifiable {
    let id: Int
    let id_auteur: Int
    let id_cible: Int
    let id_activite: Int
    let note: String
    let createdAt: String
    let auteurPseudo: String
    let ciblePseudo: String
    let titreActivite: String
    let dateActivite: String
}

// MARK: - PlayerTrakView

struct PlayerTrakView: View {
    let pseudo: String       // joueur dont on consulte les notes
    let activityId: Int      // activité courante (0 si aucune)

    @Environment(\.dismiss) private var dismiss

    @State private var notes: [TrakNote] = []
    @State private var isLoading    = false
    @State private var errorMessage: String? = nil
    @State private var newNoteText  = ""
    @State private var isSending    = false
    @State private var isAdmin      = false
    @State private var viewedPlayerId: Int = 0

    // Filtres
    @State private var filterRole:       String = "auteur"   // "auteur" | "cible"
    @State private var filterAuteur:     String = ""
    @State private var filterActiviteId: Int    = 0

    private let baseURL  = "https://viendez.com/api/trak-notes.php"
    private var myPseudo: String { AuthService.shared.pseudo }
    private var myToken:  String { AuthService.shared.token ?? "" }

    // Valeurs uniques pour les pickers
    private var uniqueAuteurs: [String] {
        var seen = Set<String>()
        return notes.compactMap { n in
            let a = n.auteurPseudo
            return seen.insert(a).inserted ? a : nil
        }.sorted()
    }

    private var uniqueDestinataires: [String] {
        var seen = Set<String>()
        return notes.compactMap { n in
            let c = n.ciblePseudo
            return seen.insert(c).inserted ? c : nil
        }.sorted()
    }

    // Selon le mode, le filtre "auteur" cible soit le destinataire soit l'auteur
    private var filterPersonLabel: String  { filterRole == "auteur" ? "Destinataire" : "Auteur" }
    private var filterPersonAll:   String  { filterRole == "auteur" ? "Tous les destinataires" : "Tous les auteurs" }
    private var filterPersonList:  [String] { filterRole == "auteur" ? uniqueDestinataires : uniqueAuteurs }

    private var uniqueActivites: [(id: Int, label: String)] {
        var seen = Set<Int>()
        return notes.compactMap { n -> (Int, String)? in
            guard n.id_activite > 0 else { return nil }
            guard seen.insert(n.id_activite).inserted else { return nil }
            let label = n.dateActivite.isEmpty ? n.titreActivite : "\(n.dateActivite) — \(n.titreActivite)"
            return (n.id_activite, label)
        }.sorted { $0.label < $1.label }
    }

    private var filteredNotes: [TrakNote] {
        notes.filter { n in
            let roleMatch: Bool
            if viewedPlayerId > 0 {
                roleMatch = filterRole == "auteur"
                    ? n.id_auteur == viewedPlayerId
                    : n.id_cible  == viewedPlayerId
            } else {
                roleMatch = filterRole == "auteur"
                    ? n.auteurPseudo.lowercased() == pseudo.lowercased()
                    : n.auteurPseudo.lowercased() != pseudo.lowercased()
            }
            // filterAuteur filtre le destinataire (cible) en mode "écrites", l'auteur en mode "reçues"
            let personMatch: Bool
            if filterAuteur.isEmpty {
                personMatch = true
            } else if filterRole == "auteur" {
                personMatch = n.ciblePseudo  == filterAuteur
            } else {
                personMatch = n.auteurPseudo == filterAuteur
            }
            return roleMatch && personMatch &&
                   (filterActiviteId == 0 || n.id_activite == filterActiviteId)
        }
    }

    // MARK: - Body

    var body: some View {
        VStack(spacing: 0) {
            if isLoading {
                Spacer()
                ProgressView("Chargement…").padding()
                Spacer()
            } else if let err = errorMessage {
                Spacer()
                VStack(spacing: 12) {
                    Image(systemName: "exclamationmark.triangle")
                        .font(.system(size: 40))
                        .foregroundColor(.orange)
                    Text(err)
                        .foregroundColor(.secondary)
                        .multilineTextAlignment(.center)
                    Button("Réessayer") { Task { await loadNotes() } }
                        .buttonStyle(.borderedProminent)
                }
                .padding()
                Spacer()
            } else {
                // ── Barre de filtres ──────────────────────────────────
                if !notes.isEmpty {
                    filterBar
                    Divider()
                }

                // Liste filtrée
                List {
                    if filteredNotes.isEmpty {
                        Text(notes.isEmpty ? "Aucune note pour ce joueur." : "Aucun résultat pour ces filtres.")
                            .foregroundColor(.secondary)
                            .padding(.vertical, 8)
                    } else {
                        ForEach(filteredNotes) { note in
                            noteRow(note)
                        }
                    }
                }
                .listStyle(.plain)

                Divider()
                addNoteArea
            }
        }
        .navigationTitle("Notes – \(pseudo)")
        .navigationBarTitleDisplayMode(.inline)
        .onAppear { Task { await loadNotes() } }
        .onChange(of: isAdmin) { admin in
            if !admin { filterRole = "auteur" }
        }
    }

    // MARK: - Note row

    @ViewBuilder
    private func noteRow(_ note: TrakNote) -> some View {
        // En mode "Écrites" on affiche la cible ; en mode "Reçues" on affiche l'auteur
        let displayPseudo = filterRole == "auteur" ? note.ciblePseudo : note.auteurPseudo
        let isHighlighted = displayPseudo.lowercased() == pseudo.lowercased()

        VStack(alignment: .leading, spacing: 6) {
            HStack {
                Button {
                    filterAuteur = (filterAuteur == displayPseudo) ? "" : displayPseudo
                } label: {
                    Text(displayPseudo)
                        .font(.caption.bold())
                        .foregroundColor(filterAuteur == displayPseudo ? .accentColor : isHighlighted ? .accentColor.opacity(0.7) : .secondary)
                        .underline(filterAuteur == displayPseudo)
                }
                .buttonStyle(.plain)
                Spacer()
                Text(formatDate(note.createdAt))
                    .font(.caption2)
                    .foregroundColor(.secondary)
            }
            if isAdmin {
                Text("auteur:\(note.id_auteur)  cible:\(note.id_cible)")
                    .font(.system(size: 10, design: .monospaced))
                    .foregroundColor(.orange.opacity(0.8))
            }
            Text(note.note)
                .font(.body)
            if !note.titreActivite.isEmpty {
                let actLabel = note.dateActivite.isEmpty
                    ? note.titreActivite
                    : "\(note.dateActivite) — \(note.titreActivite)"
                Button {
                    filterActiviteId = (filterActiviteId == note.id_activite) ? 0 : note.id_activite
                } label: {
                    Text(actLabel)
                        .font(.caption2)
                        .foregroundColor(filterActiviteId == note.id_activite ? .accentColor : .secondary)
                        .underline(filterActiviteId == note.id_activite)
                        .lineLimit(1)
                }
                .buttonStyle(.plain)
            }
        }
        .padding(.vertical, 4)
        .swipeActions(edge: .trailing, allowsFullSwipe: false) {
            if note.auteurPseudo.lowercased() == myPseudo.lowercased() {
                Button(role: .destructive) {
                    Task { await deleteNote(note.id) }
                } label: {
                    Label("Supprimer", systemImage: "trash")
                }
            }
        }
    }

    // MARK: - Filter bar

    private var filterBar: some View {
        VStack(spacing: 0) {
            if isAdmin {
                Picker("", selection: $filterRole) {
                    Text("Écrites").tag("auteur")
                    Text("Reçues").tag("cible")
                }
                .pickerStyle(.segmented)
                .padding(.horizontal, 14)
                .padding(.top, 8)
                .padding(.bottom, 4)
                .onChange(of: filterRole) { _ in
                    filterAuteur     = ""
                    filterActiviteId = 0
                }
            }

        ScrollView(.horizontal, showsIndicators: false) {
            HStack(spacing: 8) {
                // Filtre personne (destinataire ou auteur selon le mode)
                Menu {
                    Button(filterPersonAll) { filterAuteur = "" }
                    Divider()
                    ForEach(filterPersonList, id: \.self) { a in
                        Button(a) { filterAuteur = a }
                    }
                } label: {
                    HStack(spacing: 4) {
                        Image(systemName: filterRole == "auteur" ? "person.fill.checkmark" : "person")
                        Text(filterAuteur.isEmpty ? filterPersonLabel : filterAuteur)
                            .lineLimit(1)
                        Image(systemName: "chevron.down").font(.caption2)
                    }
                    .font(.caption.bold())
                    .padding(.horizontal, 10)
                    .padding(.vertical, 6)
                    .background(filterAuteur.isEmpty ? Color(.systemGray5) : Color.accentColor.opacity(0.15))
                    .cornerRadius(8)
                    .foregroundColor(filterAuteur.isEmpty ? .primary : .accentColor)
                }

                // Filtre activité
                Menu {
                    Button("Toutes les activités") { filterActiviteId = 0 }
                    Divider()
                    ForEach(uniqueActivites, id: \.id) { act in
                        Button(act.label) { filterActiviteId = act.id }
                    }
                } label: {
                    HStack(spacing: 4) {
                        Image(systemName: "calendar")
                        Text(filterActiviteId == 0
                             ? "Activité"
                             : (uniqueActivites.first { $0.id == filterActiviteId }?.label ?? "Activité"))
                            .lineLimit(1)
                        Image(systemName: "chevron.down").font(.caption2)
                    }
                    .font(.caption.bold())
                    .padding(.horizontal, 10)
                    .padding(.vertical, 6)
                    .background(filterActiviteId == 0 ? Color(.systemGray5) : Color.accentColor.opacity(0.15))
                    .cornerRadius(8)
                    .foregroundColor(filterActiviteId == 0 ? .primary : .accentColor)
                }

                // Reset si filtre actif
                if !filterAuteur.isEmpty || filterActiviteId != 0 {
                    Button {
                        filterAuteur = ""
                        filterActiviteId = 0
                    } label: {
                        Image(systemName: "xmark.circle.fill")
                            .foregroundColor(.secondary)
                    }
                    .transition(.scale)
                }

                Spacer(minLength: 0)

                Text("\(filteredNotes.count)/\(notes.count)")
                    .font(.caption2)
                    .foregroundColor(.secondary)
            }
            .padding(.horizontal, 14)
            .padding(.vertical, 8)
            .animation(.easeInOut(duration: 0.2), value: filterAuteur)
            .animation(.easeInOut(duration: 0.2), value: filterActiviteId)
        }
        } // VStack
    }

    // MARK: - Add note area

    private var addNoteArea: some View {
        HStack(alignment: .bottom, spacing: 10) {
            TextField("Ajouter une note…", text: $newNoteText, axis: .vertical)
                .textFieldStyle(.roundedBorder)
                .lineLimit(1...5)

            Button {
                Task { await addNote() }
            } label: {
                if isSending {
                    ProgressView().frame(width: 28, height: 28)
                } else {
                    Image(systemName: "paperplane.fill")
                        .font(.title3)
                        .foregroundColor(
                            newNoteText.trimmingCharacters(in: .whitespacesAndNewlines).isEmpty
                                ? .secondary : .accentColor
                        )
                }
            }
            .disabled(
                newNoteText.trimmingCharacters(in: .whitespacesAndNewlines).isEmpty || isSending
            )
        }
        .padding(.horizontal, 14)
        .padding(.vertical, 10)
    }

    // MARK: - Network

    private func loadNotes() async {
        isLoading    = true
        errorMessage = nil
        defer { isLoading = false }

        guard let encoded = pseudo.addingPercentEncoding(withAllowedCharacters: .urlQueryAllowed),
              let url = URL(string: "\(baseURL)?pseudo=\(encoded)") else { return }

        var request = URLRequest(url: url)
        request.setValue("Bearer \(myToken)", forHTTPHeaderField: "Authorization")

        do {
            let (data, _) = try await URLSession.shared.data(for: request)
            if let json = try JSONSerialization.jsonObject(with: data) as? [String: Any],
               let success = json["success"] as? Bool, success,
               let list = json["notes"] as? [[String: Any]] {
                isAdmin        = json["is_admin"] as? Bool ?? false
                viewedPlayerId = json["id_cible"]  as? Int  ?? 0
                notes          = list.compactMap { parseNote($0) }
            } else {
                errorMessage = "Impossible de charger les notes"
            }
        } catch {
            errorMessage = "Réseau indisponible"
        }
    }

    private func addNote() async {
        let text = newNoteText.trimmingCharacters(in: .whitespacesAndNewlines)
        guard !text.isEmpty else { return }

        isSending = true
        defer { isSending = false }

        guard let url = URL(string: baseURL) else { return }

        var request = URLRequest(url: url)
        request.httpMethod = "POST"
        request.setValue("application/json",      forHTTPHeaderField: "Content-Type")
        request.setValue("Bearer \(myToken)",      forHTTPHeaderField: "Authorization")

        let body: [String: Any] = [
            "action":       "add",
            "pseudo_cible": pseudo,
            "note":         text,
            "id_activite":  activityId,
        ]

        do {
            request.httpBody = try JSONSerialization.data(withJSONObject: body)
            let (data, _) = try await URLSession.shared.data(for: request)
            if let json = try JSONSerialization.jsonObject(with: data) as? [String: Any],
               let success = json["success"] as? Bool, success,
               let noteDict = json["note"] as? [String: Any],
               let newNote = parseNote(noteDict) {
                notes.insert(newNote, at: 0)
                newNoteText = ""
            }
        } catch {
            // échec silencieux
        }
    }

    private func deleteNote(_ id: Int) async {
        guard let url = URL(string: baseURL) else { return }

        var request = URLRequest(url: url)
        request.httpMethod = "POST"
        request.setValue("application/json", forHTTPHeaderField: "Content-Type")
        request.setValue("Bearer \(myToken)", forHTTPHeaderField: "Authorization")

        let body: [String: Any] = ["action": "delete", "id": id]

        do {
            request.httpBody = try JSONSerialization.data(withJSONObject: body)
            let (data, _) = try await URLSession.shared.data(for: request)
            if let json = try JSONSerialization.jsonObject(with: data) as? [String: Any],
               let success = json["success"] as? Bool, success {
                notes.removeAll { $0.id == id }
            }
        } catch {
            // échec silencieux
        }
    }

    // MARK: - Helpers

    private func parseNote(_ d: [String: Any]) -> TrakNote? {
        guard let id       = d["id"]   as? Int,
              let noteText = d["note"] as? String else { return nil }
        return TrakNote(
            id:             id,
            id_auteur:      d["id_auteur"]      as? Int    ?? 0,
            id_cible:       d["id_cible"]       as? Int    ?? 0,
            id_activite:    d["id_activite"]    as? Int    ?? 0,
            note:           noteText,
            createdAt:      d["created_at"]     as? String ?? "",
            auteurPseudo:   d["auteur_pseudo"]  as? String ?? "",
            ciblePseudo:    d["cible_pseudo"]   as? String ?? "",
            titreActivite:  d["titre_activite"] as? String ?? "",
            dateActivite:   d["date_activite"]  as? String ?? ""
        )
    }

    private func formatDate(_ str: String) -> String {
        let input  = DateFormatter()
        input.dateFormat = "yyyy-MM-dd HH:mm:ss"
        input.locale = Locale(identifier: "fr_FR")
        let output = DateFormatter()
        output.dateFormat = "dd/MM/yy HH:mm"
        output.locale = Locale(identifier: "fr_FR")
        if let date = input.date(from: str) {
            return output.string(from: date)
        }
        return str
    }
}
