import Foundation

struct BlindLevel: Codable, Identifiable, Hashable {
    var id: UUID = UUID()
    var level: Int
    var smallBlind: Int
    var bigBlind: Int
    var ante: Int
    var duration: Int // seconds

    var durationMinutes: Int {
        max(1, duration / 60)
    }

    static let defaults: [BlindLevel] = [
        BlindLevel(level: 1, smallBlind: 100, bigBlind: 200, ante: 0, duration: 1200),
        BlindLevel(level: 2, smallBlind: 200, bigBlind: 400, ante: 0, duration: 1200),
        BlindLevel(level: 3, smallBlind: 300, bigBlind: 600, ante: 0, duration: 1200),
        BlindLevel(level: 4, smallBlind: 400, bigBlind: 800, ante: 0, duration: 1200),
        BlindLevel(level: 5, smallBlind: 500, bigBlind: 1000, ante: 0, duration: 1200),
        BlindLevel(level: 6, smallBlind: 600, bigBlind: 1200, ante: 0, duration: 1200),
        BlindLevel(level: 7, smallBlind: 800, bigBlind: 1600, ante: 0, duration: 1200),
        BlindLevel(level: 8, smallBlind: 0, bigBlind: 0, ante: 0, duration: 600),
        BlindLevel(level: 9, smallBlind: 1000, bigBlind: 2000, ante: 0, duration: 900),
        BlindLevel(level: 10, smallBlind: 1500, bigBlind: 3000, ante: 0, duration: 900),
        BlindLevel(level: 11, smallBlind: 2000, bigBlind: 4000, ante: 0, duration: 900),
        BlindLevel(level: 12, smallBlind: 3000, bigBlind: 6000, ante: 0, duration: 900),
        BlindLevel(level: 13, smallBlind: 4000, bigBlind: 8000, ante: 0, duration: 900),
        BlindLevel(level: 14, smallBlind: 0, bigBlind: 0, ante: 0, duration: 0),
        BlindLevel(level: 15, smallBlind: 5000, bigBlind: 10000, ante: 0, duration: 900),
        BlindLevel(level: 16, smallBlind: 8000, bigBlind: 16000, ante: 0, duration: 900),
        BlindLevel(level: 17, smallBlind: 10000, bigBlind: 20000, ante: 0, duration: 900),
        BlindLevel(level: 18, smallBlind: 15000, bigBlind: 30000, ante: 0, duration: 900),
        BlindLevel(level: 19, smallBlind: 20000, bigBlind: 40000, ante: 0, duration: 3600)
    ]
    static let defaults2: [BlindLevel] = [
        BlindLevel(level: 1, smallBlind: 100, bigBlind: 200, ante: 0, duration: 1200),
        BlindLevel(level: 2, smallBlind: 200, bigBlind: 400, ante: 0, duration: 1200),
        BlindLevel(level: 3, smallBlind: 300, bigBlind: 600, ante: 0, duration: 1200),
        BlindLevel(level: 4, smallBlind: 400, bigBlind: 800, ante: 0, duration: 1200),
        BlindLevel(level: 5, smallBlind: 500, bigBlind: 1000, ante: 0, duration: 1200),
        BlindLevel(level: 6, smallBlind: 600, bigBlind: 1200, ante: 0, duration: 1200),
        BlindLevel(level: 7, smallBlind: 800, bigBlind: 1600, ante: 0, duration: 1200),
        BlindLevel(level: 8, smallBlind: 0, bigBlind: 0, ante: 0, duration: 600),
        BlindLevel(level: 9, smallBlind: 1000, bigBlind: 2000, ante: 0, duration: 1200),
        BlindLevel(level: 10, smallBlind: 1500, bigBlind: 3000, ante: 0, duration: 1200),
        BlindLevel(level: 11, smallBlind: 2000, bigBlind: 4000, ante: 0, duration: 1200),
        BlindLevel(level: 12, smallBlind: 3000, bigBlind: 6000, ante: 0, duration: 1200),
        BlindLevel(level: 13, smallBlind: 4000, bigBlind: 8000, ante: 0, duration: 1200),
        BlindLevel(level: 14, smallBlind: 0, bigBlind: 0, ante: 0, duration: 0),
        BlindLevel(level: 15, smallBlind: 5000, bigBlind: 10000, ante: 0, duration: 1200),
        BlindLevel(level: 16, smallBlind: 8000, bigBlind: 16000, ante: 0, duration: 1200),
        BlindLevel(level: 17, smallBlind: 10000, bigBlind: 20000, ante: 0, duration: 1200),
        BlindLevel(level: 18, smallBlind: 15000, bigBlind: 30000, ante: 0, duration: 1200),
        BlindLevel(level: 19, smallBlind: 20000, bigBlind: 40000, ante: 0, duration: 3600)
    ]
}
