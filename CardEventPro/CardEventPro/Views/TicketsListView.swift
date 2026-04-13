import SwiftUI

struct TicketItem: Identifiable, Decodable {
    var id = UUID()
    let collection_id: Int
    let name: String
    let value: Double
    let date: String
    let aff_rake: Int
}

struct TicketsListView: View {
    let memberId: Int
    let pseudo: String
    @State private var tickets: [TicketItem] = []
        @State private var monthlyTotals: [String: Int] = [:]
    @State private var isLoading = false
    @State private var error: String? = nil
    @State private var expandedSections: Set<String> = []
    @Environment(\.dismiss) private var dismiss

    var body: some View {
        NavigationView {
            Group {
                if isLoading {
                    ProgressView().frame(maxWidth: .infinity, maxHeight: .infinity)
                } else if let e = error {
                    VStack(spacing: 12) { Text(e).foregroundColor(.secondary); Button("Réessayer") { Task { await load() } } }
                } else if tickets.isEmpty {
                    Text("Aucun ticket trouvé").foregroundColor(.secondary)
                } else {
                    List {
                        ForEach(groupedMonths, id: \.label) { section in
                            Section(header:
                                        HStack {
                                            Text(section.label).font(.headline).foregroundColor(.green)
                                            Spacer()
                                            // member's tickets count
                                            Text("\(section.items.count)")
                                                .font(.subheadline)
                                                .foregroundColor(.red)
                                                .padding(.horizontal, 8)
                                                .padding(.vertical, 4)
                                                .background(Capsule().fill(Color(UIColor.systemGray5)))
                                            // total distributed for the month (all players)
                                            Text("/ \(monthlyTotals[section.key] ?? 0)")
                                                .font(.subheadline)
                                                .foregroundColor(.secondary)
                                                .padding(.leading, 6)
                                            Button(action: {
                                                withAnimation {
                                                    if expandedSections.contains(section.label) { expandedSections.remove(section.label) }
                                                    else { expandedSections.insert(section.label) }
                                                }
                                            }) {
                                                Image(systemName: expandedSections.contains(section.label) ? "chevron.down" : "chevron.right")
                                                    .foregroundColor(.secondary)
                                            }
                                            .buttonStyle(.plain)
                                        }
                                    ) {
                                    if expandedSections.contains(section.label) {
                                        ForEach(section.items) { t in
                                            HStack(spacing: 12) {
                                                Image(systemName: "ticket.fill")
                                                    .foregroundColor(.accentColor)
                                                    .frame(width: 18, height: 18)
                                                (Text(t.name).font(.subheadline).bold() + Text(" • \(formattedDate(t))").font(.caption2).foregroundColor(.secondary))
                                                    .lineLimit(1)
                                                    .truncationMode(.tail)
                                                Spacer()
                                                Text(String(format: "%.0f €", t.value))
                                                    .font(.subheadline)
                                                    .bold()
                                                    .padding(.horizontal, 4)
                                                    .padding(.vertical, 0)
                                                    .background(Capsule().fill(Color.accentColor.opacity(0.12)))
                                                    .foregroundColor(.accentColor)
                                            }
                                            .padding(.vertical, 0)
                                            .listRowBackground(Color.clear)
                                        }
                                    }
                                }
                        }
                    }
                }
            }
            .navigationTitle(pseudo.isEmpty ? "Tickets" : "Tickets de \(pseudo)")
            .toolbar {
                ToolbarItem(placement: .navigationBarTrailing) {
                    Button("Fermer") { dismiss() }
                }
            }
            .onAppear {
                Task {
                    await load()
                    // keep first month expanded by default after tickets are loaded
                    if let first = groupedMonths.first?.label {
                        expandedSections.insert(first)
                    }
                }
            }
        }
    }

    private func load() async {
        isLoading = true; error = nil
        defer { isLoading = false }
        guard let url = URL(string: "https://viendez.com/api/member-tickets.php?member_id=\(memberId)") else { error = "URL invalide"; return }
        do {
            var request = URLRequest(url: url); request.setValue("CardEvent/1.0 (iOS)", forHTTPHeaderField: "User-Agent"); if let token = KeychainHelper.read(forKey: "auth.token") { request.setValue("Bearer \(token)", forHTTPHeaderField: "Authorization") }; let (data, resp) = try await URLSession.shared.data(for: request)
            if let http = resp as? HTTPURLResponse, http.statusCode != 200 {
                let s = String(data: data, encoding: .utf8) ?? "(no body)"
                error = "Erreur serveur (code \(http.statusCode))\n\n\(s)"
                print("[TicketsList] HTTP \(http.statusCode): \(s)")
                return
            }

            if let json = try JSONSerialization.jsonObject(with: data) as? [String: Any] {
                if let success = json["success"] as? Bool, success {
                    if let arr = json["tickets"] as? [[String: Any]] {
                        tickets = arr.compactMap { dict in
                            guard let cid = dict["collection_id"] as? Int else { return nil }
                            let name = dict["name"] as? String ?? ""
                            let value = (dict["value"] as? Double) ?? Double(dict["value"] as? Int ?? 0)
                            let date = dict["date"] as? String ?? ""
                            let aff = dict["aff_rake"] as? Int ?? 0
                            return TicketItem(collection_id: cid, name: name, value: value, date: date, aff_rake: aff)
                        }
                        // parse monthly_totals if present
                        if let mt = json["monthly_totals"] as? [String: Any] {
                            var map: [String: Int] = [:]
                            for (k, v) in mt {
                                if let n = v as? Int { map[k] = n }
                                else if let s = v as? String, let i = Int(s) { map[k] = i }
                            }
                            monthlyTotals = map
                        }
                    }
                } else {
                    let serverMsg = json["error"] as? String ?? json["message"] as? String
                    let s = serverMsg ?? String(data: data, encoding: .utf8) ?? "Erreur serveur"
                    error = s
                    print("[TicketsList] server error: \(s)")
                }
            } else {
                let raw = String(data: data, encoding: .utf8) ?? "(invalid json)"
                error = "Réponse invalide du serveur"
                print("[TicketsList] invalid json: \(raw)")
            }
        } catch let err {
            error = err.localizedDescription
            print("[TicketsList] network error: \(err)")
        }
    }

    // Group tickets by month preserving the tickets' order
    // Each section also exposes a `key` in format YYYY-MM to match server monthly_totals
    private var groupedMonths: [(label: String, key: String, items: [TicketItem])] {
        guard !tickets.isEmpty else { return [] }

        let input = DateFormatter()
        input.locale = Locale(identifier: "en_US_POSIX")
        input.dateFormat = "yyyy-MM-dd HH:mm:ss"

        let monthFormatter = DateFormatter()
        monthFormatter.locale = Locale(identifier: "fr_FR")
        monthFormatter.dateFormat = "MMMM yyyy"

        var monthOrder: [Date] = []
        var monthMap: [Date: [TicketItem]] = [:]
        let cal = Calendar.current

        for t in tickets {
            var ticketDate: Date? = nil
            if let d = input.date(from: t.date) { ticketDate = d }
            else {
                // try shorter form yyyy-MM-dd
                let alt = DateFormatter()
                alt.locale = Locale(identifier: "en_US_POSIX")
                alt.dateFormat = "yyyy-MM-dd"
                ticketDate = alt.date(from: String(t.date.prefix(10)))
            }
            guard let d = ticketDate else { continue }
            let comps = cal.dateComponents([.year, .month], from: d)
            guard let monthStart = cal.date(from: comps) else { continue }
            if !monthOrder.contains(monthStart) { monthOrder.append(monthStart) }
            monthMap[monthStart, default: []].append(t)
        }

        let keyFormatter = DateFormatter()
        keyFormatter.locale = Locale(identifier: "en_US_POSIX")
        keyFormatter.dateFormat = "yyyy-MM"

        return monthOrder.map { m in
            let label = monthFormatter.string(from: m).capitalizingFirstLetter()
            let key = keyFormatter.string(from: m)
            let items = monthMap[m] ?? []
            return (label: label, key: key, items: items)
        }
    }

    private func formattedDate(_ t: TicketItem) -> String {
        let input = DateFormatter()
        input.locale = Locale(identifier: "en_US_POSIX")
        input.dateFormat = "yyyy-MM-dd HH:mm:ss"
        var ticketDate: Date? = input.date(from: t.date)
        if ticketDate == nil {
            let alt = DateFormatter()
            alt.locale = Locale(identifier: "en_US_POSIX")
            alt.dateFormat = "yyyy-MM-dd"
            ticketDate = alt.date(from: String(t.date.prefix(10)))
        }
        guard let d = ticketDate else { return String(t.date.prefix(10)) }
        let out = DateFormatter()
        out.locale = Locale(identifier: "fr_FR")
        out.dateFormat = "dd/MM/yyyy"
        return out.string(from: d)
    }
}

fileprivate extension String {
    func capitalizingFirstLetter() -> String {
        guard let first = first else { return self }
        return String(first).uppercased() + dropFirst()
    }
}
