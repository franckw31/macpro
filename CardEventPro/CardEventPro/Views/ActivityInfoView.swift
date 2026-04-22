import SwiftUI

// MARK: - Model
struct ActivityInfo {
    let id: Int
    let title: String
    let date: String         // "yyyy-MM-dd HH:mm:ss"
    let lieu: String
    let organisateur: String
    let buyin: Int
    let rake: Int
    let rakeLabel: String
    let bounty: Int
    let recave: Int
    let recaveMontant: Int
    let recaveJetons: Int
    let jetons: Int
    let maxJoueurs: Int
    let nbTables: Int
    let inscrits: Int
    let rue: String
    let structureNom: String
    let structureDetail: String
    let structure: [ActivityLevel]
}

struct ActivityLevel: Identifiable {
    let id = UUID()
    let ordre: Int
    let sb: Int
    let bb: Int
    let ante: String
    let duree: Int   // secondes
    let pause: Int
}

// MARK: - View
struct ActivityInfoView: View {
    @ObservedObject var viewModel: PokerTimerViewModel
    @ObservedObject private var auth = AuthService.shared
    @Environment(\.dismiss) private var dismiss
    @State private var showMovements = false

    var body: some View {
        NavigationView {
            Group {
                if viewModel.isFetchingActivityInfo {
                    VStack(spacing: 16) {
                        ProgressView()
                        Text("Chargement…").foregroundColor(.secondary)
                    }
                    .frame(maxWidth: .infinity, maxHeight: .infinity)
                } else if let info = viewModel.activityInfo {
                    ScrollView {
                        VStack(alignment: .leading, spacing: 16) {
                            // En-tête
                            VStack(alignment: .leading, spacing: 4) {
                                Text(info.title)
                                    .font(.title3.bold())
                                Text(formattedDate(info.date))
                                    .font(.subheadline)
                                    .foregroundColor(.secondary)
                            }
                            .frame(maxWidth: .infinity, alignment: .leading)
                            .padding(.top, 4)

                            Divider()

                            // Infos générales
                            GroupBox {
                                VStack(spacing: 0) {
                                    infoRow(icon: "person.fill", label: "Organisateur", value: info.organisateur)
                                    Divider()
                                    mapsRow(rue: info.rue, ville: info.lieu)
                                    Divider()
                                    infoRow(icon: "person.3.fill", label: "Inscrits / Max", value: "\(info.inscrits) / \(info.maxJoueurs)")
                                    Divider()
                                    infoRow(icon: "rectangle.split.3x1.fill", label: "Tables", value: "\(info.nbTables)")
                                }
                            } label: {
                                Label("Infos Partie", systemImage: "info.circle.fill")
                                    .font(.subheadline.bold())
                            }

                            // Financier
                            GroupBox {
                                VStack(spacing: 0) {
                                    infoRow(icon: "eurosign.circle.fill", label: "Buy-in", value: "\(info.buyin)€")
                                    Divider()
                                    infoRow(icon: "percent", label: "Rake (\(info.rakeLabel))", value: "\(info.rake)€")
                                    if info.bounty > 0 {
                                        Divider()
                                        infoRow(icon: "target", label: "Bounty", value: "\(info.bounty)€", valueColor: Color(red: 0.6, green: 0.2, blue: 0.8))
                                    }
                                    if info.recave > 0 {
                                        Divider()
                                        infoRow(icon: "arrow.counterclockwise.circle.fill", label: "Recave (×\(info.recave))", value: "\(info.recaveMontant)€ / \(info.recaveJetons / 1000)k jetons", valueColor: .orange)
                                    }
                                    Divider()
                                    infoRow(icon: "crown.fill", label: "Jetons départ", value: "\(info.jetons / 1000)k")
                                }
                            } label: {
                                Label("Financier", systemImage: "creditcard.fill")
                                    .font(.subheadline.bold())
                            }

                            // Structure (texte préformaté depuis structure_modele)
                            if !info.structureDetail.isEmpty || !info.structureNom.isEmpty {
                                GroupBox {
                                    VStack(alignment: .leading, spacing: 8) {
                                        if !info.structureDetail.isEmpty {
                                            Text(info.structureDetail)
                                                .font(.caption)
                                                .foregroundColor(.primary)
                                                .lineSpacing(4)
                                                .frame(maxWidth: .infinity, alignment: .leading)
                                        } else {
                                            Text("Aucun détail disponible")
                                                .font(.caption)
                                                .foregroundColor(.secondary)
                                        }
                                    }
                                    .padding(.vertical, 4)
                                } label: {
                                    Label(info.structureNom, systemImage: "list.number")
                                        .font(.subheadline.bold())
                                }
                            }

                            // Bouton inscription
                            let hasResults = viewModel.participants.contains { $0.gain != 0 }
                            if !auth.pseudo.isEmpty && !hasResults {
                                Button {
                                    Task { await viewModel.toggleRegistration() }
                                } label: {
                                    if viewModel.isRegistering {
                                        ProgressView()
                                            .scaleEffect(0.7)
                                            .tint(.white)
                                    } else {
                                        Text(viewModel.isRegistered ? "Désinscrire \(auth.pseudo)" : "Inscrire \(auth.pseudo)")
                                            .font(.subheadline.bold())
                                            .frame(maxWidth: .infinity)
                                    }
                                }
                                .buttonStyle(.borderedProminent)
                                .tint(viewModel.isRegistered ? Color.red.opacity(0.85) : Color.green.opacity(0.85))
                                .disabled(viewModel.isRegistering)
                            }

                            Spacer(minLength: 20)
                        }
                        .padding(.horizontal)
                        .padding(.top, 8)
                    }
                } else {
                    VStack(spacing: 12) {
                        Image(systemName: "exclamationmark.triangle")
                            .font(.system(size: 40))
                            .foregroundColor(.secondary)
                        Text("Informations indisponibles")
                            .foregroundColor(.secondary)
                    }
                    .frame(maxWidth: .infinity, maxHeight: .infinity)
                }
            }
            .navigationBarTitleDisplayMode(.inline)
            .toolbar {
                ToolbarItem(placement: .principal) {
                    HStack(spacing: 8) {
                        Text("Info Partie")
                            .font(.headline)
                        Spacer()
                        if viewModel.activityInfo != nil {
                            Button(action: { showMovements = true }) {
                                HStack(spacing: 4) {
                                    Image(systemName: "arrow.up.arrow.down")
                                    Text("Mouvements")
                                }
                                .font(.subheadline.bold())
                                .foregroundColor(.cyan)
                            }
                        }
                    }
                    .frame(maxWidth: .infinity)
                }
                ToolbarItem(placement: .navigationBarTrailing) {
                    Button("Fermer") { dismiss() }
                }
            }
        }
        .task {
            await viewModel.fetchActivityInfo()
        }
        .sheet(isPresented: $showMovements) {
            if let info = viewModel.activityInfo {
                PlayersMovementsView(activityId: info.id, activityTitle: info.title)
            }
        }
    }

    private func infoRow(icon: String, label: String, value: String, valueColor: Color = .primary) -> some View {
        HStack(spacing: 10) {
            Image(systemName: icon)
                .foregroundColor(.accentColor)
                .frame(width: 20)
            Text(label)
                .font(.subheadline)
                .foregroundColor(.secondary)
            Spacer()
            Text(value)
                .font(.subheadline.bold())
                .foregroundColor(valueColor)
                .multilineTextAlignment(.trailing)
        }
        .padding(.vertical, 8)
    }

    private func mapsRow(rue: String, ville: String) -> some View {
        let adresse = [rue, ville].filter { !$0.isEmpty }.joined(separator: ", ")
        let encoded = adresse.addingPercentEncoding(withAllowedCharacters: .urlQueryAllowed) ?? ""
        let mapsURL = URL(string: "maps://?q=\(encoded)")
        return HStack(spacing: 10) {
            Image(systemName: "mappin.and.ellipse")
                .foregroundColor(.accentColor)
                .frame(width: 20)
            Text("Lieu")
                .font(.subheadline)
                .foregroundColor(.secondary)
            Spacer()
            if adresse.isEmpty {
                Text("—")
                    .font(.subheadline.bold())
            } else if let url = mapsURL {
                Link(destination: url) {
                    HStack(spacing: 4) {
                        Text(adresse)
                            .font(.subheadline.bold())
                            .multilineTextAlignment(.trailing)
                        Image(systemName: "map.fill")
                            .font(.caption)
                    }
                    .foregroundColor(.accentColor)
                }
            }
        }
        .padding(.vertical, 8)
    }

    private func formattedDate(_ raw: String) -> String {
        let fmt = DateFormatter()
        fmt.locale = Locale(identifier: "fr_FR")
        fmt.dateFormat = "yyyy-MM-dd HH:mm:ss"
        guard let date = fmt.date(from: raw) else { return raw }
        fmt.dateFormat = "EEEE d MMMM 'à' HH'h'mm"
        let s = fmt.string(from: date)
        return s.prefix(1).uppercased() + s.dropFirst()
    }

    private func formatDuration(_ seconds: Int) -> String {
        let m = seconds / 60
        return "\(m) min"
    }
}
