import SwiftUI

// Wrapper identifiable pour la sheet de notes
private struct TrakTarget: Identifiable {
    let id: String  // pseudo
}

struct ParticipantsListView: View {
    @ObservedObject var viewModel: PokerTimerViewModel
    @Environment(\.dismiss) private var dismiss
    @State private var trakTarget: TrakTarget? = nil

    private let registeredStatuses: Set<String> = [
        "Inscrit", "Option",
        "Réservation", "Reservation",
        "Présent", "Present", "Confirmé", "Confirme",
        "Eliminé", "Elimine"
    ]

    private var isAuthorizedToToggle: Bool {
        if AuthService.shared.isAdmin { return true }
        if let orga = viewModel.activityInfo?.organisateur,
           !orga.isEmpty,
           orga.trimmingCharacters(in: .whitespacesAndNewlines).lowercased() == AuthService.shared.pseudo.trimmingCharacters(in: .whitespacesAndNewlines).lowercased() {
            return true
        }
        return false
    }

    /// Liste triée : par classement si au moins un participant en a un, sinon par ordre d'inscription.
    private var sortedParticipants: [Participant] {
        let hasRanking = viewModel.participants.contains { $0.classement > 0 }
        guard hasRanking else { return viewModel.participants }
        return viewModel.participants.sorted {
            // classement == 0 → non classé → va en bas
            let a = $0.classement == 0 ? Int.max : $0.classement
            let b = $1.classement == 0 ? Int.max : $1.classement
            return a < b
        }
    }

    var body: some View {
        NavigationView {
            Group {
                if viewModel.isFetchingParticipants && viewModel.participants.isEmpty {
                    VStack(spacing: 16) {
                        ProgressView()
                        Text("Chargement...")
                            .foregroundColor(.secondary)
                    }
                    .frame(maxWidth: .infinity, maxHeight: .infinity)
                } else if viewModel.participants.isEmpty {
                    VStack(spacing: 12) {
                        Image(systemName: "person.3")
                            .font(.system(size: 48))
                            .foregroundColor(.secondary)
                        Text("Aucun participant inscrit")
                            .foregroundColor(.secondary)
                    }
                    .frame(maxWidth: .infinity, maxHeight: .infinity)
                } else {
                    let hasRanking = viewModel.participants.contains { $0.classement > 0 }
                    ScrollView {
                        LazyVStack(spacing: 0) {
                            if hasRanking {
                                // En-tête des colonnes
                                HStack(spacing: 10) {
                                    Text("#")
                                        .frame(width: 36, alignment: .center)
                                    Text("Pseudo")
                                        .frame(maxWidth: .infinity, alignment: .leading)
                                    Text("Chal.")
                                        .frame(width: 38, alignment: .center)
                                    Text("Recave")
                                        .frame(width: 28, alignment: .center)
                                    Text("Bounty")
                                        .frame(width: 44, alignment: .center)
                                    Text("Gains")
                                        .frame(width: 50, alignment: .trailing)
                                }
                                .font(.caption2.bold())
                                .foregroundColor(.secondary)
                                .padding(.horizontal, 16)
                                .padding(.vertical, 6)
                                Divider()
                            }
                            ForEach(Array(sortedParticipants.enumerated()), id: \.element.id) { index, p in
                                if hasRanking {
                                    HStack(spacing: 10) {
                                        Text(p.classement > 0 ? "#\(p.classement)" : "-")
                                            .font(.system(size: 15, weight: .bold, design: .rounded))
                                            .foregroundColor(p.classement == 1 ? .yellow : p.classement == 2 ? Color(white: 0.75) : p.classement == 3 ? Color(red: 0.8, green: 0.5, blue: 0.2) : .secondary)
                                            .frame(width: 36, alignment: .center)
                                        Button { trakTarget = TrakTarget(id: p.pseudo) } label: {
                                            HStack(spacing: 5) {
                                                Text(p.pseudo)
                                                    .font(p.isMe ? .body.bold() : .body)
                                                    .foregroundColor(p.isMe ? .accentColor : .primary)
                                                    .lineLimit(1)
                                                if p.latereg == 1 { Text("(Latereg)").font(.caption2).foregroundColor(.orange) }
                                                Image(systemName: "pencil.circle")
                                                    .font(.system(size: 14))
                                                    .foregroundColor(.secondary.opacity(0.7))
                                            }
                                        }
                                        .buttonStyle(.plain)
                                        .frame(maxWidth: .infinity, alignment: .leading)
                                        Text(p.challengeRank > 0 ? "#\(p.challengeRank)" : "-")
                                            .font(.system(size: 13, weight: .bold, design: .rounded))
                                            .foregroundColor(p.challengeRank == 1 ? .yellow : p.challengeRank <= 3 ? .orange : .secondary)
                                            .frame(width: 38, alignment: .center)
                                        Text(p.recave > 0 ? "\(p.recave)" : "-")
                                            .font(.system(size: 13, weight: .semibold, design: .rounded))
                                            .foregroundColor(p.recave > 0 ? .orange : .secondary)
                                            .frame(width: 28, alignment: .center)
                                        Text(p.bounty > 0 ? "\(p.bounty)" : "-")
                                            .font(.system(size: 13, weight: .semibold, design: .rounded))
                                            .foregroundColor(p.bounty > 0 ? Color(red: 0.6, green: 0.2, blue: 0.8) : .secondary)
                                            .frame(width: 44, alignment: .center)
                                        Text(p.gain != 0 ? "\(p.gain)€" : "-")
                                            .font(.system(size: 14, weight: .semibold, design: .rounded))
                                            .foregroundColor(p.gain > 0 ? .green : p.gain < 0 ? .red : .secondary)
                                            .frame(width: 50, alignment: .trailing)
                                    }
                                    .padding(.horizontal, 16)
                                    .padding(.vertical, 9)
                                    .frame(maxWidth: .infinity)
                                    .background(p.isMe ? Color.accentColor.opacity(0.08) : Color.clear)
                                    Divider().padding(.leading, 16)
                                } else {
                                    HStack(spacing: 6) {
                                        Text("\(index + 1)")
                                            .font(.caption)
                                            .foregroundColor(.secondary)
                                            .frame(width: 20, alignment: .trailing)
                                        Button { trakTarget = TrakTarget(id: p.pseudo) } label: {
                                            HStack(spacing: 5) {
                                                Text(p.pseudo)
                                                    .font(p.isMe ? .body.bold() : .body)
                                                    .foregroundColor(p.isMe ? .accentColor : .primary)
                                                    .lineLimit(1)
                                                if p.latereg == 1 { Text("(Latereg)").font(.caption2).foregroundColor(.orange) }
                                                Image(systemName: "pencil.circle")
                                                    .font(.system(size: 14))
                                                    .foregroundColor(.secondary.opacity(0.7))
                                            }
                                        }
                                        .buttonStyle(.plain)
                                        .frame(maxWidth: .infinity, alignment: .leading)
                                        if !p.dateInscription.isEmpty {
                                            Text(p.dateInscription)
                                                .font(.caption2)
                                                .foregroundColor(.secondary)
                                                .lineLimit(1)
                                        }
                                        if p.bonus1 > 0 {
                                            Text("+\(p.bonus1)")
                                                .font(.caption2.bold())
                                                .foregroundColor(.blue)
                                        }
                                        if p.recave > 0 {
                                            Text("R×\(p.recave)")
                                                .font(.caption2)
                                                .foregroundColor(.orange)
                                                .padding(.horizontal, 4)
                                                .padding(.vertical, 2)
                                                .background(Color.orange.opacity(0.15))
                                                .cornerRadius(4)
                                        }
                                        
                                        if isAuthorizedToToggle && ["Inscrit", "Réservation", "Reservation", "Option"].contains(p.statut) {
                                            Button {
                                                print("Clic sur le badge de \(p.pseudo) !")
                                                Task {
                                                    await viewModel.toggleParticipantOption(pseudo: p.pseudo)
                                                }
                                            } label: {
                                                statutBadge(p.statut)
                                            }
                                            .buttonStyle(.plain)
                                        } else {
                                            statutBadge(p.statut)
                                                .onTapGesture {
                                                    print("Badge cliqué mais isAuthorized=\(isAuthorizedToToggle) et statut=\(p.statut)")
                                                }
                                        }
                                    }
                                    .padding(.horizontal, 16)
                                    .padding(.vertical, 9)
                                    .frame(maxWidth: .infinity)
                                    .background(p.isMe ? Color.accentColor.opacity(0.08) : Color.clear)
                                    Divider().padding(.leading, 16)
                                }
                            }
                        }
                    }
                }
            }
            .navigationBarTitleDisplayMode(.inline)
            .toolbar {
                ToolbarItem(placement: .principal) {
                    VStack(spacing: 1) {
                        if let dateFull = viewModel.nextActivityDateFull {
                            Text(dateFull)
                                .font(.caption)
                                .foregroundColor(.secondary)
                        }
                        Text("Participants (\(viewModel.participants.count))")
                            .font(.headline)
                    }
                }
                ToolbarItem(placement: .navigationBarTrailing) {
                    Button {
                        Task { await viewModel.fetchParticipants() }
                    } label: {
                        if viewModel.isFetchingParticipants {
                            ProgressView().scaleEffect(0.8)
                        } else {
                            Image(systemName: "arrow.clockwise")
                        }
                    }
                    .disabled(viewModel.isFetchingParticipants)
                }
                ToolbarItem(placement: .navigationBarLeading) {
                    Button("Fermer") { dismiss() }
                }
            }
        }
        .task {
            await viewModel.fetchParticipants()
        }
        .sheet(item: $trakTarget) { target in
            NavigationView {
                PlayerTrakView(
                    pseudo: target.id,
                    activityId: viewModel.nextActivityId ?? 0
                )
                .toolbar {
                    ToolbarItem(placement: .navigationBarLeading) {
                        Button("Fermer") { trakTarget = nil }
                    }
                }
            }
        }
    }

    @ViewBuilder
    private func statutBadge(_ statut: String) -> some View {
        let (label, color) = statutInfo(statut)
        Text(label)
            .font(.caption2.bold())
            .foregroundColor(.white)
            .padding(.horizontal, 6)
            .padding(.vertical, 3)
            .background(color)
            .cornerRadius(5)
    }

    private func statutInfo(_ statut: String) -> (String, Color) {
        switch statut {
        case "Présent", "Present":              return ("Présent", .green)
        case "Inscrit", "Réservation", "Reservation": return ("Inscrit", .blue)
        case "Confirmé", "Confirme":             return ("Confirmé", Color(red: 0, green: 0.7, blue: 0.8))
        case "Option":                           return ("Option", Color(red: 0.8, green: 0.6, blue: 0))
        case "Eliminé", "Elimine":               return ("Eliminé", .red)
        default:                                  return (statut, .gray)
        }
    }
}
