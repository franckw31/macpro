import SwiftUI

// MARK: - ManageEventView
// Gestion complète d'une partie Pro : joueurs inscrits, statut, actions

struct ManageEventView: View {

    let event: ProEvent

    @StateObject private var service = OrganizerService.shared
    @Environment(\.dismiss) private var dismiss

    @State private var registrations: [ProRegistration] = []
    @State private var isLoadingRegs = false
    @State private var showEditSheet = false
    @State private var confirmAction: ConfirmAction?

    private let gold   = Color(red: 1.0, green: 0.75, blue: 0.0)
    private let green  = Color(red: 0.2, green: 0.9, blue: 0.4)

    enum ConfirmAction: Identifiable {
        case start, finish, cancel
        var id: Int { hashValue }
        var title: String {
            switch self {
            case .start:  return "Démarrer la partie ?"
            case .finish: return "Terminer la partie ?"
            case .cancel: return "Annuler la partie ?"
            }
        }
        var buttonLabel: String {
            switch self {
            case .start:  return "Démarrer"
            case .finish: return "Terminer"
            case .cancel: return "Annuler"
            }
        }
        var role: ButtonRole? {
            switch self {
            case .start:  return nil
            case .finish: return nil
            case .cancel: return .destructive
            }
        }
        var targetStatut: ProEventStatus {
            switch self {
            case .start:  return .enCours
            case .finish: return .termine
            case .cancel: return .annule
            }
        }
    }

    var body: some View {
        NavigationStack {
            ZStack {
                Color.black.ignoresSafeArea()

                ScrollView {
                    VStack(spacing: 20) {
                        eventHeaderCard
                        actionsBar
                        registrationsSection
                    }
                    .padding()
                }
            }
            .navigationTitle(event.titre)
            .navigationBarTitleDisplayMode(.inline)
            .toolbarColorScheme(.dark, for: .navigationBar)
            .toolbar {
                ToolbarItem(placement: .navigationBarLeading) {
                    Button("Fermer") { dismiss() }
                        .foregroundColor(.gray)
                }
                ToolbarItem(placement: .navigationBarTrailing) {
                    Button {
                        showEditSheet = true
                    } label: {
                        Image(systemName: "pencil.circle")
                            .foregroundColor(gold)
                    }
                }
            }
            .task { await loadRegistrations() }
            .refreshable { await loadRegistrations() }
            .sheet(isPresented: $showEditSheet) {
                CreateEventView(mode: .edit(event)) { _ in
                    showEditSheet = false
                }
            }
            .confirmationDialog(
                confirmAction?.title ?? "",
                isPresented: Binding(
                    get: { confirmAction != nil },
                    set: { if !$0 { confirmAction = nil } }
                ),
                titleVisibility: .visible
            ) {
                if let action = confirmAction {
                    Button(action.buttonLabel, role: action.role) {
                        Task {
                            await service.changeStatus(eventId: event.id, statut: action.targetStatut)
                            dismiss()
                        }
                    }
                    Button("Annuler", role: .cancel) {}
                }
            }
        }
    }

    // MARK: - En-tête de la partie

    private var eventHeaderCard: some View {
        VStack(alignment: .leading, spacing: 12) {
            HStack {
                StatusBadge(statut: event.statut)
                Spacer()
                if event.isPublic {
                    Label("Publique", systemImage: "globe")
                        .font(.caption)
                        .foregroundColor(.gray)
                } else {
                    Label("Privée", systemImage: "lock.fill")
                        .font(.caption)
                        .foregroundColor(.gray)
                }
            }

            Divider().background(Color.white.opacity(0.1))

            infoRow(icon: "mappin.circle.fill",  text: event.lieu)
            infoRow(icon: "calendar",             text: formattedDate(event.dateEvent))
            infoRow(icon: "eurosign.circle.fill", text: "\(String(format: "%.0f", event.buyIn)) \(event.devise) buy-in")

            Divider().background(Color.white.opacity(0.1))

            // Jauge de remplissage
            let ratio = min(1.0, Double(event.nbInscrits) / Double(max(1, event.maxJoueurs)))
            HStack {
                Text("Inscrits")
                    .font(.caption)
                    .foregroundColor(.gray)
                Spacer()
                Text("\(event.nbInscrits) / \(event.maxJoueurs)")
                    .font(.caption.bold())
                    .foregroundColor(ratio >= 1.0 ? .red : gold)
            }
            GeometryReader { geo in
                ZStack(alignment: .leading) {
                    Capsule().fill(Color.white.opacity(0.1)).frame(height: 6)
                    Capsule()
                        .fill(ratio >= 1.0 ? Color.red : gold)
                        .frame(width: geo.size.width * ratio, height: 6)
                }
            }
            .frame(height: 6)
        }
        .padding()
        .background(Color(white: 0.1))
        .cornerRadius(14)
    }

    // MARK: - Barre d'actions rapides

    @ViewBuilder
    private var actionsBar: some View {
        HStack(spacing: 12) {
            switch event.statut {
            case .brouillon:
                actionButton("Publier", icon: "checkmark.circle", color: .blue) {
                    Task { await service.changeStatus(eventId: event.id, statut: .publie) }
                }
            case .publie:
                actionButton("Démarrer", icon: "play.fill", color: green) {
                    confirmAction = .start
                }
                actionButton("Annuler", icon: "xmark.circle", color: .red) {
                    confirmAction = .cancel
                }
            case .enCours:
                actionButton("Terminer", icon: "flag.checkered", color: .purple) {
                    confirmAction = .finish
                }
                actionButton("Mettre en pause", icon: "pause.fill", color: .orange) {
                    Task { await service.changeStatus(eventId: event.id, statut: .publie) }
                }
            case .termine, .annule:
                Text("Partie \(event.statut.label.lowercased())")
                    .font(.subheadline)
                    .foregroundColor(.gray)
                    .frame(maxWidth: .infinity)
            }
        }
    }

    // MARK: - Liste des inscrits

    private var registrationsSection: some View {
        VStack(alignment: .leading, spacing: 12) {
            HStack {
                Label("Joueurs inscrits", systemImage: "person.2.fill")
                    .font(.headline)
                    .foregroundColor(.white)
                Spacer()
                if isLoadingRegs {
                    ProgressView().tint(gold).scaleEffect(0.7)
                }
            }

            if registrations.isEmpty && !isLoadingRegs {
                Text("Aucun joueur inscrit pour l'instant.")
                    .font(.subheadline)
                    .foregroundColor(.gray)
                    .frame(maxWidth: .infinity, alignment: .center)
                    .padding()
            } else {
                ForEach(registrations) { reg in
                    registrationRow(reg)
                }
            }
        }
        .padding()
        .background(Color(white: 0.1))
        .cornerRadius(14)
    }

    private func registrationRow(_ reg: ProRegistration) -> some View {
        HStack {
            Image(systemName: "person.crop.circle.fill")
                .font(.title3)
                .foregroundColor(gold.opacity(0.7))

            VStack(alignment: .leading, spacing: 2) {
                Text(reg.pseudo)
                    .font(.subheadline.bold())
                    .foregroundColor(.white)
                Text("Inscrit le \(shortDate(reg.inscritLe))")
                    .font(.caption2)
                    .foregroundColor(.gray)
            }
            Spacer()
            statutBadge(reg.statut)
        }
        .padding(.vertical, 6)
        .overlay(Divider(), alignment: .bottom)
    }

    // MARK: - Helpers visuels

    private func infoRow(icon: String, text: String) -> some View {
        HStack(spacing: 8) {
            Image(systemName: icon).foregroundColor(gold).frame(width: 18)
            Text(text).font(.subheadline).foregroundColor(.white)
        }
    }

    private func actionButton(_ title: String, icon: String, color: Color, action: @escaping () -> Void) -> some View {
        Button(action: action) {
            VStack(spacing: 4) {
                Image(systemName: icon).font(.title3)
                Text(title).font(.caption.bold())
            }
            .foregroundColor(color)
            .frame(maxWidth: .infinity)
            .padding(.vertical, 12)
            .background(color.opacity(0.15))
            .cornerRadius(12)
        }
    }

    private func statutBadge(_ statut: String) -> some View {
        let (text, color): (String, Color) = {
            switch statut {
            case "confirme":        return ("Confirmé", .green)
            case "liste_attente":   return ("En attente", .orange)
            case "absent":          return ("Absent", .red)
            default:                return ("Inscrit", .blue)
            }
        }()
        return Text(text)
            .font(.caption2.bold())
            .foregroundColor(color)
            .padding(.horizontal, 8)
            .padding(.vertical, 3)
            .background(color.opacity(0.2))
            .clipShape(Capsule())
    }

    private func formattedDate(_ str: String) -> String {
        let fmtIn  = DateFormatter(); fmtIn.dateFormat  = "yyyy-MM-dd HH:mm:ss"
        let fmtOut = DateFormatter(); fmtOut.dateFormat = "dd/MM/yyyy à HH:mm"
        fmtOut.locale = Locale(identifier: "fr_FR")
        if let d = fmtIn.date(from: str) { return fmtOut.string(from: d) }
        return str
    }

    private func shortDate(_ str: String) -> String {
        let fmtIn  = DateFormatter(); fmtIn.dateFormat  = "yyyy-MM-dd HH:mm:ss"
        let fmtOut = DateFormatter(); fmtOut.dateStyle  = .short; fmtOut.timeStyle = .none
        fmtOut.locale = Locale(identifier: "fr_FR")
        if let d = fmtIn.date(from: str) { return fmtOut.string(from: d) }
        return str
    }

    // MARK: - Data

    private func loadRegistrations() async {
        isLoadingRegs = true
        defer { isLoadingRegs = false }
        registrations = await service.fetchRegistrations(eventId: event.id)
    }
}
