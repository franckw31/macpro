import Foundation

// MARK: - Organizer

struct Organizer: Codable, Identifiable {
    let id: Int
    let pseudo: String
    let email: String
    let isVerified: Bool
    let createdAt: String

    enum CodingKeys: String, CodingKey {
        case id, pseudo, email
        case isVerified  = "is_verified"
        case createdAt   = "created_at"
    }
}

// MARK: - ProEvent (partie créée par un organisateur)

struct ProEvent: Codable, Identifiable {
    let id: Int
    var titre: String
    var description: String
    var lieu: String
    var dateEvent: String       // "YYYY-MM-DD HH:MM"
    var maxJoueurs: Int
    var buyIn: Double
    var devise: String          // "EUR", "USD", …
    var statut: ProEventStatus
    var isPublic: Bool
    var organizerId: Int
    var organizerPseudo: String
    var activityId: Int?        // lié à une activité CardEvent existante
    var nbInscrits: Int
    var createdAt: String

    enum CodingKeys: String, CodingKey {
        case id, titre, description, lieu, devise, statut
        case dateEvent       = "date_event"
        case maxJoueurs      = "max_joueurs"
        case buyIn           = "buy_in"
        case isPublic        = "is_public"
        case organizerId     = "organizer_id"
        case organizerPseudo = "organizer_pseudo"
        case activityId      = "activity_id"
        case nbInscrits      = "nb_inscrits"
        case createdAt       = "created_at"
    }
}

enum ProEventStatus: String, Codable, CaseIterable {
    case brouillon  = "brouillon"
    case publie     = "publie"
    case enCours    = "en_cours"
    case termine    = "termine"
    case annule     = "annule"

    var label: String {
        switch self {
        case .brouillon: return "Brouillon"
        case .publie:    return "Publié"
        case .enCours:   return "En cours"
        case .termine:   return "Terminé"
        case .annule:    return "Annulé"
        }
    }

    var color: String {
        switch self {
        case .brouillon: return "gray"
        case .publie:    return "blue"
        case .enCours:   return "green"
        case .termine:   return "purple"
        case .annule:    return "red"
        }
    }
}

// MARK: - NewEventForm (données du formulaire de création)

struct NewEventForm {
    var titre: String = ""
    var description: String = ""
    var lieu: String = ""
    var dateEvent: Date = Date().addingTimeInterval(7 * 86400) // +7 jours
    var maxJoueurs: Int = 20
    var buyIn: Double = 20.0
    var devise: String = "EUR"
    var isPublic: Bool = true
    var structureId: Int? = nil     // structure de blindes optionnelle

    var isValid: Bool {
        !titre.trimmingCharacters(in: .whitespaces).isEmpty &&
        !lieu.trimmingCharacters(in: .whitespaces).isEmpty &&
        maxJoueurs >= 2 &&
        buyIn >= 0
    }
}

// MARK: - ProRegistration (inscription d'un joueur à une partie Pro)

struct ProRegistration: Codable, Identifiable {
    let id: Int
    let eventId: Int
    let memberId: Int
    let pseudo: String
    let statut: String      // "inscrit", "liste_attente", "confirme", "absent"
    let inscritLe: String

    enum CodingKeys: String, CodingKey {
        case id, pseudo, statut
        case eventId   = "event_id"
        case memberId  = "member_id"
        case inscritLe = "inscrit_le"
    }
}

// MARK: - API Response wrappers

struct ProEventListResponse: Codable {
    let success: Bool
    let events: [ProEvent]
    let message: String?
}

struct ProEventResponse: Codable {
    let success: Bool
    let event: ProEvent?
    let message: String?
}

struct ProRegistrationListResponse: Codable {
    let success: Bool
    let registrations: [ProRegistration]
    let message: String?
}

struct ProActionResponse: Codable {
    let success: Bool
    let message: String?
    let eventId: Int?

    enum CodingKeys: String, CodingKey {
        case success, message
        case eventId = "event_id"
    }
}
