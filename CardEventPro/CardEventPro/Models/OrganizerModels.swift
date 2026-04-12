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
    var dateEvent: String
    var maxJoueurs: Int
    var buyIn: Double
    var devise: String
    var statut: ProEventStatus
    var isPublic: Bool
    var organizerId: Int
    var organizerPseudo: String
    var activityId: Int?
    var nbInscrits: Int
    var createdAt: String
    // Parametres poker
    var structureId: Int
    var rake: Int
    var bounty: Int
    var jetons: Int
    var nbRecaves: Int
    var recaveMontant: Int
    var recaveJetons: Int
    var bonus: Int
    var nbTables: Int

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
        case structureId     = "structure_id"
        case rake, bounty, jetons, bonus
        case nbRecaves       = "nb_recaves"
        case recaveMontant   = "recave_montant"
        case recaveJetons    = "recave_jetons"
        case nbTables        = "nb_tables"
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
    var dateEvent: Date = Date().addingTimeInterval(7 * 86400)
    var maxJoueurs: Int = 20
    var buyIn: Double = 20.0
    var devise: String = "EUR"
    var isPublic: Bool = true
    // Parametres poker
    var structureId: Int = 1
    var rake: Int = 5
    var bounty: Int = 0
    var jetons: Int = 35000
    var nbRecaves: Int = 1
    var recaveMontant: Int = 10
    var recaveJetons: Int = 40000
    var bonus: Int = 0
    var nbTables: Int = 2

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
    let photoUrl: String
    let statut: String      // "inscrit", "liste_attente", "confirme", "absent"
    let isPrivate: Bool
    let inscritLe: String

    enum CodingKeys: String, CodingKey {
        case id, pseudo, statut
        case eventId   = "event_id"
        case memberId  = "member_id"
        case photoUrl  = "photo_url"
        case isPrivate = "is_private"
        case inscritLe = "inscrit_le"
    }
}

// MARK: - MemberSearchResult (résultat de recherche dans membres)

struct MemberSearchResult: Codable, Identifiable {
    let id: Int
    let pseudo: String
    let fname: String
    let lname: String
    let email: String
    let photoUrl: String
    let isRegistered: Bool
    let regStatut: String

    enum CodingKeys: String, CodingKey {
        case pseudo, fname, lname, email
        case id          = "member_id"
        case photoUrl    = "photo_url"
        case isRegistered = "is_registered"
        case regStatut   = "reg_statut"
    }

    var fullName: String {
        let full = "\(fname) \(lname)".trimmingCharacters(in: .whitespaces)
        return full.isEmpty ? pseudo : full
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

struct MemberSearchResponse: Codable {
    let success: Bool
    let members: [MemberSearchResult]
    let message: String?
}

struct CreateMemberResponse: Codable {
    let success: Bool
    let memberId: Int?
    let pseudo: String?
    let message: String?

    enum CodingKeys: String, CodingKey {
        case success, pseudo, message
        case memberId = "member_id"
    }
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
