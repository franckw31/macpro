import Foundation

struct BlindStructure: Codable, Identifiable {
    var id: UUID = UUID()
    var name: String
    var levels: [BlindLevel]

    static let defaultStructures: [BlindStructure] = [
        BlindStructure(name: "Semaine (7*20 + 11*15)", levels: BlindLevel.defaults),
        BlindStructure(name: "Week-End (18*20)", levels: BlindLevel.defaults2)
    ]
}

final class BlindStructureStore: ObservableObject {
    static let shared = BlindStructureStore()

    @Published var structures: [BlindStructure] = []
    private let key = "cardevent.blindStructures"

    private init() {
        load()
        if structures.isEmpty {
            structures = BlindStructure.defaultStructures
            save()
        } else {
            var changed = false
            for defaultStructure in BlindStructure.defaultStructures {
                if !structures.contains(where: { $0.name == defaultStructure.name }) {
                    structures.append(defaultStructure)
                    changed = true
                }
            }
            if changed { save() }
        }
    }

    func save() {
        if let data = try? JSONEncoder().encode(structures) { UserDefaults.standard.set(data, forKey: key) }
    }

    func load() {
        guard let data = UserDefaults.standard.data(forKey: key), let decoded = try? JSONDecoder().decode([BlindStructure].self, from: data) else { return }
        structures = decoded
    }

    func add(_ structure: BlindStructure) { structures.append(structure); save() }
    func update(_ structure: BlindStructure) { guard let idx = structures.firstIndex(where: { $0.id == structure.id }) else { return }; structures[idx] = structure; save() }
    func delete(at offsets: IndexSet) { structures.remove(atOffsets: offsets); if structures.isEmpty { structures = BlindStructure.defaultStructures }; save() }
}
