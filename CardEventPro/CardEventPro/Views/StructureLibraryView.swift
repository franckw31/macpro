import SwiftUI

struct StructureLibraryView: View {
    @Environment(\.dismiss) private var dismiss
    @ObservedObject private var store = BlindStructureStore.shared

    let currentLevels: [BlindLevel]
    let onLoad: ([BlindLevel], String) -> Void

    @State private var editingStructure: BlindStructure? = nil
    @State private var showNewStructure = false
    @State private var renamingStructure: BlindStructure? = nil
    @State private var newName = ""

    var body: some View {
        NavigationStack {
            List {
                ForEach(store.structures) { structure in
                    StructureRow(
                        structure: structure,
                        isActive: structure.levels == currentLevels,
                        onLoad: {
                            onLoad(structure.levels, structure.name)
                            dismiss()
                        },
                        onEdit: {
                            editingStructure = structure
                        },
                        onRename: {
                            renamingStructure = structure
                            newName = structure.name
                        }
                    )
                }
                .onDelete(perform: store.delete)
            }
            .navigationTitle("Structures")
            .toolbar {
                ToolbarItem(placement: .cancellationAction) {
                    Button("Fermer") { dismiss() }
                }
                ToolbarItem(placement: .primaryAction) {
                    Button {
                        showNewStructure = true
                    } label: {
                        Label("Nouvelle", systemImage: "plus")
                    }
                }
            }
            // Éditer une structure existante
            .sheet(item: $editingStructure) { structure in
                EditStructureView(levels: structure.levels) { updated in
                    var copy = structure
                    copy.levels = updated
                    store.update(copy)
                } onDelete: {
                    if let idx = store.structures.firstIndex(where: { $0.id == structure.id }) {
                        store.delete(at: IndexSet(integer: idx))
                    }
                }
            }
            // Créer une nouvelle structure (copie de la structure active)
            .sheet(isPresented: $showNewStructure) {
                NewStructureView(baseLevels: currentLevels) { name, levels in
                    let newStruct = BlindStructure(name: name, levels: levels)
                    store.add(newStruct)
                }
            }
            // Renommer une structure
            .alert("Renommer", isPresented: Binding(
                get: { renamingStructure != nil },
                set: { if !$0 { renamingStructure = nil } }
            )) {
                TextField("Nom", text: $newName)
                Button("OK") {
                    if var s = renamingStructure, !newName.trimmingCharacters(in: .whitespaces).isEmpty {
                        s.name = newName.trimmingCharacters(in: .whitespaces)
                        store.update(s)
                    }
                    renamingStructure = nil
                }
                Button("Annuler", role: .cancel) { renamingStructure = nil }
            }
        }
    }
}

// MARK: - Structure Row

private struct StructureRow: View {
    let structure: BlindStructure
    let isActive: Bool
    let onLoad: () -> Void
    let onEdit: () -> Void
    let onRename: () -> Void

    var body: some View {
        HStack {
            VStack(alignment: .leading, spacing: 3) {
                HStack(spacing: 6) {
                    if isActive {
                        Image(systemName: "checkmark.circle.fill")
                            .foregroundStyle(.green)
                            .font(.caption)
                    }
                    Text(structure.name)
                        .font(.headline)
                }
                Text("\(structure.levels.count) niveaux")
                    .font(.caption)
                    .foregroundStyle(.secondary)
            }
            Spacer()
            // Bouton Charger
            Button("Charger") { onLoad() }
                .buttonStyle(.borderedProminent)
                .controlSize(.small)
                .tint(isActive ? .gray : .blue)
                .disabled(isActive)

            // Menu Éditer / Renommer
            Menu {
                Button { onEdit() } label: {
                    Label("Éditer les niveaux", systemImage: "pencil")
                }
                Button { onRename() } label: {
                    Label("Renommer", systemImage: "textformat")
                }
            } label: {
                Image(systemName: "ellipsis.circle")
                    .foregroundStyle(.secondary)
            }
            .buttonStyle(.plain)
        }
        .padding(.vertical, 4)
    }
}

// MARK: - New Structure View

struct NewStructureView: View {
    @Environment(\.dismiss) private var dismiss
    let baseLevels: [BlindLevel]
    let onCreate: (String, [BlindLevel]) -> Void

    @State private var name = ""
    @State private var levels: [BlindLevel]

    init(baseLevels: [BlindLevel], onCreate: @escaping (String, [BlindLevel]) -> Void) {
        self.baseLevels = baseLevels
        self.onCreate = onCreate
        _levels = State(initialValue: baseLevels)
    }

    var body: some View {
        NavigationStack {
            List {
                Section("Nom de la structure") {
                    TextField("Ex: Tournoi rapide", text: $name)
                }
                Section("Basée sur") {
                    Text("Structure actuelle (\(levels.count) niveaux)")
                        .foregroundStyle(.secondary)
                }
            }
            .navigationTitle("Nouvelle structure")
            .toolbar {
                ToolbarItem(placement: .cancellationAction) {
                    Button("Annuler") { dismiss() }
                }
                ToolbarItem(placement: .confirmationAction) {
                    Button("Créer") {
                        let finalName = name.trimmingCharacters(in: .whitespaces)
                        onCreate(finalName.isEmpty ? "Nouvelle structure" : finalName, levels)
                        dismiss()
                    }
                }
            }
        }
    }
}
