import SwiftUI

struct EditStructureView: View {
    @Environment(\.dismiss) private var dismiss
    @State private var levels: [BlindLevel]
    let onSave: ([BlindLevel]) -> Void
    let onDelete: (() -> Void)?

    init(levels: [BlindLevel], onSave: @escaping ([BlindLevel]) -> Void, onDelete: (() -> Void)? = nil) {
        _levels = State(initialValue: levels)
        self.onSave = onSave
        self.onDelete = onDelete
    }

    var body: some View {
        NavigationStack {
            List {
                ForEach($levels) { $level in
                    Section {
                        Stepper("Small blind: \(level.smallBlind)", value: $level.smallBlind, in: 0...2_000_000, step: 25)
                        Stepper("Big blind: \(level.bigBlind)", value: $level.bigBlind, in: 0...4_000_000, step: 50)
                        Stepper("Ante: \(level.ante)", value: $level.ante, in: 0...1_000_000, step: 25)
                        Stepper(
                            "Durée: \(max(1, level.duration / 60)) min",
                            value: Binding(
                                get: { max(1, level.duration / 60) },
                                set: { level.duration = max(1, $0) * 60 }
                            ),
                            in: 1...180
                        )
                    } header: {
                        HStack {
                            Text("Niveau \(level.level)")
                                .font(.headline)
                            Spacer()
                            // Insérer un niveau après celui-ci
                            Button {
                                insertLevel(after: level)
                            } label: {
                                Image(systemName: "plus.circle.fill")
                                    .foregroundStyle(.green)
                            }
                            .buttonStyle(.plain)
                            // Supprimer ce niveau
                            Button {
                                deleteLevel(level)
                            } label: {
                                Image(systemName: "minus.circle.fill")
                                    .foregroundStyle(.red)
                            }
                            .buttonStyle(.plain)
                            .disabled(levels.count <= 1)
                        }
                    }
                }
                .onDelete(perform: delete)

                if let onDelete {
                    Button(role: .destructive) {
                        onDelete()
                        dismiss()
                    } label: {
                        Label("Supprimer cette structure", systemImage: "trash")
                            .frame(maxWidth: .infinity, alignment: .center)
                    }
                }
            }
            .navigationTitle("Structure blinds")
            .toolbar {
                ToolbarItem(placement: .cancellationAction) {
                    Button("Annuler") { dismiss() }
                }
                ToolbarItem(placement: .confirmationAction) {
                    Button("Sauver") {
                        normalizeLevels()
                        onSave(levels)
                        dismiss()
                    }
                }
            }
        }
    }

    private func insertLevel(after level: BlindLevel) {
        guard let idx = levels.firstIndex(where: { $0.id == level.id }) else { return }
        let base = levels[idx]
        let next = idx + 1 < levels.count ? levels[idx + 1] : nil
        let newSmall = next != nil ? (base.smallBlind + next!.smallBlind) / 2 : base.smallBlind + 100
        let newBig   = next != nil ? (base.bigBlind  + next!.bigBlind)  / 2 : base.bigBlind  + 200
        let new = BlindLevel(
            level: idx + 2,
            smallBlind: max(25, newSmall),
            bigBlind:   max(50, newBig),
            ante:       base.ante,
            duration:   max(60, base.duration)
        )
        levels.insert(new, at: idx + 1)
        normalizeLevels()
    }

    private func deleteLevel(_ level: BlindLevel) {
        guard levels.count > 1,
              let idx = levels.firstIndex(where: { $0.id == level.id }) else { return }
        levels.remove(at: idx)
        normalizeLevels()
    }

    private func addLevel() {
        let base = levels.last ?? BlindLevel(level: 1, smallBlind: 25, bigBlind: 50, ante: 0, duration: 900)
        let new = BlindLevel(
            level: levels.count + 1,
            smallBlind: max(25, base.smallBlind + 100),
            bigBlind: max(50, base.bigBlind + 200),
            ante: base.ante,
            duration: max(60, base.duration)
        )
        levels.append(new)
        normalizeLevels()
    }

    private func delete(at offsets: IndexSet) {
        levels.remove(atOffsets: offsets)
        if levels.isEmpty {
            levels = [BlindLevel(level: 1, smallBlind: 25, bigBlind: 50, ante: 0, duration: 900)]
        }
        normalizeLevels()
    }

    private func normalizeLevels() {
        for index in levels.indices {
            levels[index].level = index + 1
            levels[index].duration = max(60, levels[index].duration)
        }
    }
}
