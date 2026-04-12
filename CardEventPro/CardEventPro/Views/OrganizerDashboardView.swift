import SwiftUI

// MARK: - OrganizerDashboardView
// Tableau de bord principal des organisateurs — onglet exclusif Pro

struct OrganizerDashboardView: View {

    @StateObject private var service = OrganizerService.shared
    @EnvironmentObject  private var auth: AuthService

    @State private var showCreateSheet = false
    @State private var selectedEvent: ProEvent?
    @State private var showDeleteConfirm = false
    @State private var eventToDelete: ProEvent?

    private let gold = Color(red: 1.0, green: 0.75, blue: 0.0)

    var body: some View {
        NavigationStack {
            ZStack {
                Color.black.ignoresSafeArea()

                if service.isLoading && service.myEvents.isEmpty {
                    loadingView
                } else if service.myEvents.isEmpty {
                    emptyView
                } else {
                    eventList
                }
            }
            .navigationTitle("Mes Parties")
            .navigationBarTitleDisplayMode(.large)
            .toolbarColorScheme(.dark, for: .navigationBar)
            .toolbar {
                ToolbarItem(placement: .navigationBarTrailing) {
                    Button {
                        showCreateSheet = true
                    } label: {
                        Image(systemName: "plus.circle.fill")
                            .font(.title3)
                            .foregroundColor(gold)
                    }
                }
                ToolbarItem(placement: .navigationBarLeading) {
                    Text("👑 Pro")
                        .font(.caption.bold())
                        .foregroundColor(gold)
                        .padding(.horizontal, 8)
                        .padding(.vertical, 4)
                        .background(gold.opacity(0.15))
                        .clipShape(Capsule())
                }
            }
            .refreshable {
                await service.fetchMyEvents()
            }
            .task {
                await service.fetchMyEvents()
            }
            .sheet(isPresented: $showCreateSheet) {
                CreateEventView(mode: .create) { _ in
                    showCreateSheet = false
                }
                .environmentObject(auth)
            }
            .sheet(item: $selectedEvent) { event in
                ManageEventView(event: event)
                    .environmentObject(auth)
            }
            .confirmationDialog(
                "Supprimer cette partie ?",
                isPresented: $showDeleteConfirm,
                titleVisibility: .visible
            ) {
                Button("Supprimer", role: .destructive) {
                    if let ev = eventToDelete {
                        Task { await service.deleteEvent(eventId: ev.id) }
                    }
                }
            } message: {
                Text(eventToDelete?.titre ?? "")
            }
            .alert("Erreur", isPresented: Binding(
                get: { service.errorMessage != nil },
                set: { if !$0 { service.errorMessage = nil } }
            )) {
                Button("OK", role: .cancel) { service.errorMessage = nil }
            } message: {
                Text(service.errorMessage ?? "")
            }
        }
    }

    // MARK: - Sous-vues

    private var loadingView: some View {
        VStack(spacing: 16) {
            ProgressView().tint(gold).scaleEffect(1.4)
            Text("Chargement…").foregroundColor(.gray)
        }
    }

    private var emptyView: some View {
        VStack(spacing: 20) {
            Image(systemName: "calendar.badge.plus")
                .font(.system(size: 60))
                .foregroundColor(gold.opacity(0.6))
            Text("Aucune partie créée")
                .font(.title3.bold())
                .foregroundColor(.white)
            Text("Appuyez sur + pour créer\nvotre première partie.")
                .font(.subheadline)
                .multilineTextAlignment(.center)
                .foregroundColor(.gray)
            Button {
                showCreateSheet = true
            } label: {
                Label("Créer une partie", systemImage: "plus")
                    .font(.headline)
                    .foregroundColor(.black)
                    .padding(.horizontal, 28)
                    .padding(.vertical, 12)
                    .background(gold)
                    .clipShape(Capsule())
            }
        }
        .padding()
    }

    private var eventList: some View {
        List {
            ForEach(service.myEvents) { event in
                ProEventRowView(event: event)
                    .listRowBackground(Color(white: 0.1))
                    .listRowInsets(EdgeInsets(top: 6, leading: 12, bottom: 6, trailing: 12))
                    .contentShape(Rectangle())
                    .onTapGesture { selectedEvent = event }
                    .swipeActions(edge: .trailing, allowsFullSwipe: false) {
                        Button(role: .destructive) {
                            eventToDelete = event
                            showDeleteConfirm = true
                        } label: {
                            Label("Supprimer", systemImage: "trash")
                        }
                    }
                    .swipeActions(edge: .leading) {
                        // Publier / Mettre en brouillon
                        if event.statut == .brouillon || event.statut == .annule {
                            Button {
                                Task { await service.changeStatus(eventId: event.id, statut: .publie) }
                            } label: {
                                Label("Publier", systemImage: "checkmark.circle")
                            }
                            .tint(.blue)
                        } else if event.statut == .publie {
                            Button {
                                Task { await service.changeStatus(eventId: event.id, statut: .enCours) }
                            } label: {
                                Label("Démarrer", systemImage: "play.fill")
                            }
                            .tint(.green)
                        }
                    }
            }
        }
        .listStyle(.plain)
        .background(Color.black)
        .scrollContentBackground(.hidden)
    }
}

// MARK: - ProEventRowView

struct ProEventRowView: View {
    let event: ProEvent

    private let gold = Color(red: 1.0, green: 0.75, blue: 0.0)

    var body: some View {
        VStack(alignment: .leading, spacing: 8) {
            HStack {
                Text(event.titre)
                    .font(.headline)
                    .foregroundColor(.white)
                    .lineLimit(1)
                Spacer()
                StatusBadge(statut: event.statut)
            }

            HStack(spacing: 16) {
                Label(event.lieu, systemImage: "mappin.circle.fill")
                    .font(.caption)
                    .foregroundColor(.gray)
                    .lineLimit(1)

                Spacer()

                Label("\(event.nbInscrits)/\(event.maxJoueurs)", systemImage: "person.2.fill")
                    .font(.caption.bold())
                    .foregroundColor(event.nbInscrits >= event.maxJoueurs ? .red : gold)
            }

            HStack {
                Label(formattedDate(event.dateEvent), systemImage: "calendar")
                    .font(.caption2)
                    .foregroundColor(.gray)
                Spacer()
                Text("\(String(format: "%.0f", event.buyIn)) \(event.devise) buy-in")
                    .font(.caption2.bold())
                    .foregroundColor(gold)
            }
        }
        .padding(.vertical, 4)
    }

    private func formattedDate(_ str: String) -> String {
        let fmtIn  = DateFormatter()
        fmtIn.dateFormat = "yyyy-MM-dd HH:mm:ss"
        let fmtOut = DateFormatter()
        fmtOut.dateFormat = "dd/MM/yyyy à HH:mm"
        fmtOut.locale = Locale(identifier: "fr_FR")
        if let d = fmtIn.date(from: str) { return fmtOut.string(from: d) }
        return str
    }
}

// MARK: - StatusBadge

struct StatusBadge: View {
    let statut: ProEventStatus

    private var bgColor: Color {
        switch statut {
        case .brouillon: return Color.gray.opacity(0.4)
        case .publie:    return Color.blue.opacity(0.4)
        case .enCours:   return Color.green.opacity(0.4)
        case .termine:   return Color.purple.opacity(0.4)
        case .annule:    return Color.red.opacity(0.4)
        }
    }

    private var fgColor: Color {
        switch statut {
        case .brouillon: return .gray
        case .publie:    return .blue
        case .enCours:   return .green
        case .termine:   return .purple
        case .annule:    return .red
        }
    }

    var body: some View {
        Text(statut.label)
            .font(.caption2.bold())
            .foregroundColor(fgColor)
            .padding(.horizontal, 8)
            .padding(.vertical, 3)
            .background(bgColor)
            .clipShape(Capsule())
    }
}
