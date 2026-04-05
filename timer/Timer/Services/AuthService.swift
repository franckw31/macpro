import Foundation
import Security
#if canImport(UIKit)
import UIKit
#endif

// MARK: - Keychain Helper

enum KeychainHelper {
    private static let service = "com.cardevent.auth"

    static func save(_ value: String, forKey key: String) {
        guard let data = value.data(using: .utf8) else { return }
        let query: [CFString: Any] = [
            kSecClass:       kSecClassGenericPassword,
            kSecAttrService: service,
            kSecAttrAccount: key,
            kSecValueData:   data,
        ]
        SecItemDelete(query as CFDictionary)
        SecItemAdd(query as CFDictionary, nil)
    }

    static func read(forKey key: String) -> String? {
        let query: [CFString: Any] = [
            kSecClass:            kSecClassGenericPassword,
            kSecAttrService:      service,
            kSecAttrAccount:      key,
            kSecReturnData:       true,
            kSecMatchLimit:       kSecMatchLimitOne,
        ]
        var result: AnyObject?
        let status = SecItemCopyMatching(query as CFDictionary, &result)
        guard status == errSecSuccess,
              let data = result as? Data,
              let string = String(data: data, encoding: .utf8) else { return nil }
        return string
    }

    static func delete(forKey key: String) {
        let query: [CFString: Any] = [
            kSecClass:       kSecClassGenericPassword,
            kSecAttrService: service,
            kSecAttrAccount: key,
        ]
        SecItemDelete(query as CFDictionary)
    }
}

// MARK: - Auth Service

@MainActor
final class AuthService: ObservableObject {

    static let shared = AuthService()

    // ── Published State ──────────────────────────────────────────
    @Published var isAuthenticated = false
    @Published var isLoading       = false
    @Published var errorMessage: String?
    @Published var pseudo: String  = ""
    @Published var isAdmin: Bool   = false

    // ── Constants ────────────────────────────────────────────────
    private let authURL    = "https://viendez.com/api/auth.php"
    private let tokenKey   = "auth.token"
    private let pseudoKey  = "auth.pseudo"
    private let adminKey   = "auth.isAdmin"

    private init() {}

    /// Token Bearer stocké dans le Keychain (lecture seule pour les autres services).
    var token: String? { KeychainHelper.read(forKey: tokenKey) }

    // ── Public API ───────────────────────────────────────────────

    /// Appelé au lancement : tente de valider un token sauvegardé.
    func autoLogin() async {
        guard let token = KeychainHelper.read(forKey: tokenKey) else { return }
        isLoading = true
        defer { isLoading = false }

        do {
            let ok = try await verifyToken(token)
            if ok {
                pseudo   = KeychainHelper.read(forKey: pseudoKey) ?? ""
                isAdmin  = KeychainHelper.read(forKey: adminKey) == "1"
                isAuthenticated = true
            } else {
                clearCredentials()
            }
        } catch {
            // Pas de réseau → on laisse l'accès si le token existe déjà
            pseudo   = KeychainHelper.read(forKey: pseudoKey) ?? ""
            isAdmin  = KeychainHelper.read(forKey: adminKey) == "1"
            isAuthenticated = true
            print("AuthService: réseau indisponible, accès hors-ligne autorisé")
        }
    }

    /// Connexion manuelle avec identifiants.
    func login(username: String, password: String) async {
        guard !username.isEmpty, !password.isEmpty else {
            errorMessage = "Veuillez remplir tous les champs"
            return
        }
        isLoading     = true
        errorMessage  = nil
        defer { isLoading = false }

        guard let url = URL(string: authURL) else { return }

        var request = URLRequest(url: url)
        request.httpMethod = "POST"
        request.setValue("application/json", forHTTPHeaderField: "Content-Type")
        request.timeoutInterval = 10

        let payload: [String: Any] = [
            "action":    "login",
            "username":  username,
            "password":  password,
            "device_id": deviceId(),
        ]
        request.httpBody = try? JSONSerialization.data(withJSONObject: payload)

        do {
            let (data, response) = try await URLSession.shared.data(for: request)

            if let http = response as? HTTPURLResponse, http.statusCode != 200 {
                let msg = jsonError(from: data) ?? "Erreur \(http.statusCode)"
                errorMessage = msg
                return
            }

            guard let json = try? JSONSerialization.jsonObject(with: data) as? [String: Any],
                  let success = json["success"] as? Bool, success,
                  let token   = json["token"]   as? String,
                  let name    = json["pseudo"]   as? String
            else {
                errorMessage = jsonError(from: data) ?? "Réponse inattendue du serveur"
                return
            }

            KeychainHelper.save(token, forKey: tokenKey)
            KeychainHelper.save(name,  forKey: pseudoKey)
            let adminFlag = (json["is_admin"] as? Bool == true) ? "1" : "0"
            KeychainHelper.save(adminFlag, forKey: adminKey)
            pseudo          = name
            isAdmin         = adminFlag == "1"
            isAuthenticated = true

        } catch {
            errorMessage = "Impossible de joindre le serveur"
        }
    }

    /// Déconnexion : révoque le token côté serveur et vide le Keychain.
    func logout() async {
        if let token = KeychainHelper.read(forKey: tokenKey) {
            try? await sendLogout(token: token)
        }
        clearCredentials()
    }

    // ── Private Helpers ──────────────────────────────────────────

    private func verifyToken(_ token: String) async throws -> Bool {
        guard let url = URL(string: authURL) else { return false }
        var request = URLRequest(url: url)
        request.httpMethod = "POST"
        request.setValue("application/json", forHTTPHeaderField: "Content-Type")
        request.timeoutInterval = 6

        let payload = ["action": "verify_token", "token": token, "device_id": deviceId()]
        request.httpBody = try? JSONSerialization.data(withJSONObject: payload)

        let (data, _) = try await URLSession.shared.data(for: request)
        guard let json = try? JSONSerialization.jsonObject(with: data) as? [String: Any],
              let success = json["success"] as? Bool else { return false }
        if success, let p = json["pseudo"] as? String {
            KeychainHelper.save(p, forKey: pseudoKey)
            let adminFlag = (json["is_admin"] as? Bool == true) ? "1" : "0"
            KeychainHelper.save(adminFlag, forKey: adminKey)
        }
        return success
    }

    private func sendLogout(token: String) async throws {
        guard let url = URL(string: authURL) else { return }
        var request = URLRequest(url: url)
        request.httpMethod = "POST"
        request.setValue("application/json", forHTTPHeaderField: "Content-Type")
        request.timeoutInterval = 5
        let payload = ["action": "logout", "token": token]
        request.httpBody = try? JSONSerialization.data(withJSONObject: payload)
        _ = try await URLSession.shared.data(for: request)
    }

    private func clearCredentials() {
        KeychainHelper.delete(forKey: tokenKey)
        KeychainHelper.delete(forKey: pseudoKey)
        KeychainHelper.delete(forKey: adminKey)
        pseudo          = ""
        isAdmin         = false
        isAuthenticated = false
    }

    private func jsonError(from data: Data) -> String? {
        guard let json = try? JSONSerialization.jsonObject(with: data) as? [String: Any],
              let err = json["error"] as? String else { return nil }
        return err
    }

    private func deviceId() -> String {
        #if canImport(UIKit)
        return UIDevice.current.identifierForVendor?.uuidString ?? "unknown"
        #else
        return "unknown"
        #endif
    }
}
