import Foundation

// MARK: - OrganizerService
// Toutes les API calls spécifiques à CardEvent Pro (organisateurs)

@MainActor
final class OrganizerService: ObservableObject {
    static let shared = OrganizerService()

    @Published var myEvents:   [ProEvent] = []
    @Published var publicEvents: [ProEvent] = []
    @Published var isLoading   = false
    @Published var errorMessage: String?

    private let baseURL = "https://viendez.com/api/pro"

    private init() {}

    // MARK: - Auth header helper

    private func authorizedRequest(url: URL, method: String = "GET") -> URLRequest {
        var req = URLRequest(url: url)
        req.httpMethod = method
        req.setValue("application/json", forHTTPHeaderField: "Content-Type")
        req.timeoutInterval = 15
        if let token = KeychainHelper.authToken {
            req.setValue("Bearer \(token)", forHTTPHeaderField: "Authorization")
        }
        return req
    }

    // MARK: - Fetch: mes parties (organisateur)

    func fetchMyEvents() async {
        guard let url = URL(string: "\(baseURL)/my-events.php") else { return }
        isLoading = true
        defer { isLoading = false }
        do {
            let (data, _) = try await URLSession.shared.data(for: authorizedRequest(url: url))
            let response = try JSONDecoder().decode(ProEventListResponse.self, from: data)
            if response.success {
                myEvents = response.events
            } else {
                errorMessage = response.message ?? "Erreur inconnue"
            }
        } catch {
            errorMessage = "Erreur réseau: \(error.localizedDescription)"
        }
    }

    // MARK: - Fetch: toutes les parties publiques

    func fetchPublicEvents() async {
        guard let url = URL(string: "\(baseURL)/public-events.php") else { return }
        isLoading = true
        defer { isLoading = false }
        do {
            let (data, _) = try await URLSession.shared.data(for: authorizedRequest(url: url))
            let response = try JSONDecoder().decode(ProEventListResponse.self, from: data)
            if response.success {
                publicEvents = response.events
            } else {
                errorMessage = response.message ?? "Erreur inconnue"
            }
        } catch {
            errorMessage = "Erreur réseau: \(error.localizedDescription)"
        }
    }

    // MARK: - Créer une nouvelle partie

    func createEvent(form: NewEventForm) async -> Result<ProEvent, Error> {
        guard let url = URL(string: "\(baseURL)/create-event.php") else {
            return .failure(APIError.invalidURL)
        }
        var req = authorizedRequest(url: url, method: "POST")

        let formatter = ISO8601DateFormatter()
        formatter.formatOptions = [.withInternetDateTime]
        let dateStr = formatter.string(from: form.dateEvent)

        let body: [String: Any] = [
            "titre":          form.titre,
            "description":    form.description,
            "lieu":           form.lieu,
            "date_event":     dateStr,
            "max_joueurs":    form.maxJoueurs,
            "buy_in":         form.buyIn,
            "devise":         form.devise,
            "is_public":      form.isPublic,
            "structure_id":   form.structureId,
            "rake":           form.rake,
            "bounty":         form.bounty,
            "jetons":         form.jetons,
            "nb_recaves":     form.nbRecaves,
            "recave_montant": form.recaveMontant,
            "recave_jetons":  form.recaveJetons,
            "bonus":          form.bonus,
            "nb_tables":      form.nbTables
        ]
        req.httpBody = try? JSONSerialization.data(withJSONObject: body)

        do {
            let (data, _) = try await URLSession.shared.data(for: req)
            let response = try JSONDecoder().decode(ProEventResponse.self, from: data)
            if response.success, let event = response.event {
                await fetchMyEvents()
                return .success(event)
            } else {
                return .failure(APIError.serverError(response.message ?? "Erreur serveur"))
            }
        } catch {
            return .failure(error)
        }
    }

    // MARK: - Modifier une partie existante

    func updateEvent(id: Int, form: NewEventForm) async -> Result<ProEvent, Error> {
        guard let url = URL(string: "\(baseURL)/update-event.php") else {
            return .failure(APIError.invalidURL)
        }
        var req = authorizedRequest(url: url, method: "POST")

        let formatter = ISO8601DateFormatter()
        formatter.formatOptions = [.withInternetDateTime]
        let dateStr = formatter.string(from: form.dateEvent)

        let body: [String: Any] = [
            "event_id":       id,
            "titre":          form.titre,
            "description":    form.description,
            "lieu":           form.lieu,
            "date_event":     dateStr,
            "max_joueurs":    form.maxJoueurs,
            "buy_in":         form.buyIn,
            "devise":         form.devise,
            "is_public":      form.isPublic,
            "structure_id":   form.structureId,
            "rake":           form.rake,
            "bounty":         form.bounty,
            "jetons":         form.jetons,
            "nb_recaves":     form.nbRecaves,
            "recave_montant": form.recaveMontant,
            "recave_jetons":  form.recaveJetons,
            "bonus":          form.bonus,
            "nb_tables":      form.nbTables
        ]
        req.httpBody = try? JSONSerialization.data(withJSONObject: body)

        do {
            let (data, _) = try await URLSession.shared.data(for: req)
            let response = try JSONDecoder().decode(ProEventResponse.self, from: data)
            if response.success, let event = response.event {
                await fetchMyEvents()
                return .success(event)
            } else {
                return .failure(APIError.serverError(response.message ?? "Erreur serveur"))
            }
        } catch {
            return .failure(error)
        }
    }

    // MARK: - Changer le statut d'une partie

    func changeStatus(eventId: Int, statut: ProEventStatus) async -> Bool {
        guard let url = URL(string: "\(baseURL)/change-status.php") else { return false }
        var req = authorizedRequest(url: url, method: "POST")
        req.httpBody = try? JSONSerialization.data(withJSONObject: [
            "event_id": eventId,
            "statut":   statut.rawValue
        ])
        do {
            let (data, _) = try await URLSession.shared.data(for: req)
            let response = try JSONDecoder().decode(ProActionResponse.self, from: data)
            if response.success { await fetchMyEvents() }
            return response.success
        } catch {
            errorMessage = error.localizedDescription
            return false
        }
    }

    // MARK: - Supprimer une partie

    func deleteEvent(eventId: Int) async -> Bool {
        guard let url = URL(string: "\(baseURL)/delete-event.php") else { return false }
        var req = authorizedRequest(url: url, method: "POST")
        req.httpBody = try? JSONSerialization.data(withJSONObject: ["event_id": eventId])
        do {
            let (data, _) = try await URLSession.shared.data(for: req)
            let response = try JSONDecoder().decode(ProActionResponse.self, from: data)
            if response.success { await fetchMyEvents() }
            return response.success
        } catch {
            return false
        }
    }

    // MARK: - Inscrire/désinscrire un joueur

    func registerPlayer(eventId: Int, memberId: Int) async -> Bool {
        return await playerAction(eventId: eventId, memberId: memberId, action: "register")
    }

    func unregisterPlayer(eventId: Int, memberId: Int) async -> Bool {
        return await playerAction(eventId: eventId, memberId: memberId, action: "unregister")
    }

    private func playerAction(eventId: Int, memberId: Int, action: String) async -> Bool {
        guard let url = URL(string: "\(baseURL)/player-registration.php") else { return false }
        var req = authorizedRequest(url: url, method: "POST")
        req.httpBody = try? JSONSerialization.data(withJSONObject: [
            "event_id":  eventId,
            "member_id": memberId,
            "action":    action
        ])
        do {
            let (data, _) = try await URLSession.shared.data(for: req)
            let response = try JSONDecoder().decode(ProActionResponse.self, from: data)
            return response.success
        } catch {
            return false
        }
    }

    // MARK: - Récupérer les inscrits d'une partie

    func fetchRegistrations(eventId: Int) async -> [ProRegistration] {
        guard let url = URL(string: "\(baseURL)/event-participants.php?event_id=\(eventId)") else { return [] }
        do {
            let (data, _) = try await URLSession.shared.data(for: authorizedRequest(url: url))
            let response = try JSONDecoder().decode(ProRegistrationListResponse.self, from: data)
            return response.success ? response.registrations : []
        } catch {
            return []
        }
    }
}

// MARK: - Erreurs API

enum APIError: LocalizedError {
    case invalidURL
    case serverError(String)

    var errorDescription: String? {
        switch self {
        case .invalidURL:          return "URL invalide"
        case .serverError(let m):  return m
        }
    }
}
