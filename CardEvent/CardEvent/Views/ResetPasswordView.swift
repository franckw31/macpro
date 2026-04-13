import SwiftUI

// MARK: - ResetPasswordView

struct ResetPasswordView: View {
    let token: String

    @Environment(\.dismiss) private var dismiss

    @State private var password        = ""
    @State private var passwordConfirm = ""
    @State private var isLoading       = false
    @State private var errorMessage: String?
    @State private var resetSuccess    = false
    @State private var isTokenError    = false
    @FocusState private var focusedField: Field?

    private enum Field { case password, passwordConfirm }

    private let cyan    = Color(red: 0, green: 0.82, blue: 1)
    private let baseURL = "https://viendez.com/api/forgot-password.php"

    private var isFormValid: Bool {
        password.count >= 6 && password == passwordConfirm
    }

    // MARK: - Body

    var body: some View {
        ZStack {
            Color.black.ignoresSafeArea()

            if resetSuccess {
                successView
            } else {
                formView
            }
        }
        .preferredColorScheme(.dark)
    }

    // MARK: - Formulaire

    private var formView: some View {
        VStack(spacing: 0) {
            Spacer()

            Image(systemName: "lock.open.fill")
                .font(.system(size: 56))
                .foregroundColor(cyan)
                .shadow(color: cyan.opacity(0.5), radius: 14)
                .padding(.bottom, 20)

            Text("Nouveau mot de passe")
                .font(.title2.bold())
                .foregroundColor(.white)
                .padding(.bottom, 8)

            Text("Choisissez un mot de passe d'au moins 6 caractères.")
                .font(.subheadline)
                .foregroundColor(.white.opacity(0.55))
                .multilineTextAlignment(.center)
                .padding(.horizontal, 32)
                .padding(.bottom, 32)

            VStack(spacing: 12) {
                AuthTextField(
                    icon:        "lock.fill",
                    placeholder: "Nouveau mot de passe",
                    text:        $password,
                    isSecure:    true
                )
                .focused($focusedField, equals: .password)
                .submitLabel(.next)
                .onSubmit { focusedField = .passwordConfirm }

                HStack(spacing: 8) {
                    AuthTextField(
                        icon:        "lock.badge.checkmark.fill",
                        placeholder: "Confirmer le mot de passe",
                        text:        $passwordConfirm,
                        isSecure:    true
                    )
                    .focused($focusedField, equals: .passwordConfirm)
                    .submitLabel(.done)
                    .onSubmit { attemptReset() }

                    if !passwordConfirm.isEmpty {
                        Image(systemName: password == passwordConfirm
                              ? "checkmark.circle.fill" : "xmark.circle.fill")
                            .foregroundColor(password == passwordConfirm ? .green : .red)
                            .font(.title3)
                    }
                }
            }
            .padding(.horizontal, 28)
            .padding(.bottom, 12)

            if let error = errorMessage {
                HStack(spacing: 8) {
                    Image(systemName: "exclamationmark.triangle.fill")
                    Text(error).font(.footnote)
                }
                .foregroundColor(.orange)
                .padding(.horizontal, 28)
                .multilineTextAlignment(.center)
                .padding(.bottom, 4)

                // Si le lien est invalide/expiré, proposer une nouvelle demande
                if isTokenError {
                    Button(action: { dismiss() }) {
                        Text("← Faire une nouvelle demande")
                            .font(.footnote.bold())
                            .foregroundColor(cyan)
                    }
                    .padding(.bottom, 12)
                } else {
                    Spacer().frame(height: 12)
                }
            }

            Button(action: attemptReset) {
                ZStack {
                    if isLoading {
                        ProgressView().tint(.white)
                    } else {
                        Text("Enregistrer le mot de passe")
                            .font(.headline)
                            .foregroundColor(.white)
                    }
                }
                .frame(maxWidth: .infinity)
                .frame(height: 50)
                .background(isFormValid && !isLoading ? cyan : cyan.opacity(0.35))
                .clipShape(RoundedRectangle(cornerRadius: 14))
                .shadow(color: cyan.opacity(0.3), radius: 8)
            }
            .disabled(!isFormValid || isLoading)
            .padding(.horizontal, 28)
            .padding(.bottom, 16)

            Button(action: { dismiss() }) {
                Text("Annuler")
                    .font(.footnote)
                    .foregroundColor(.white.opacity(0.45))
                    .padding(.vertical, 8)
            }

            Spacer()
        }
        .frame(maxWidth: 480)
        .frame(maxWidth: .infinity)
    }

    // MARK: - Écran de succès

    private var successView: some View {
        VStack(spacing: 24) {
            Spacer()

            Image(systemName: "checkmark.shield.fill")
                .font(.system(size: 72))
                .foregroundColor(.green)
                .shadow(color: Color.green.opacity(0.4), radius: 20)

            Text("Mot de passe modifié !")
                .font(.title2.bold())
                .foregroundColor(.white)

            Text("Votre mot de passe a bien été mis à jour.\nVous pouvez maintenant vous connecter.")
                .font(.subheadline)
                .foregroundColor(.white.opacity(0.7))
                .multilineTextAlignment(.center)
                .padding(.horizontal, 32)
                .lineSpacing(4)

            Spacer()

            Button(action: { dismiss() }) {
                Text("Se connecter")
                    .font(.headline)
                    .foregroundColor(.white)
                    .frame(maxWidth: .infinity)
                    .frame(height: 50)
                    .background(cyan)
                    .clipShape(RoundedRectangle(cornerRadius: 14))
                    .shadow(color: cyan.opacity(0.4), radius: 8)
            }
            .padding(.horizontal, 28)
            .padding(.bottom, 48)
        }
    }

    // MARK: - Action

    private func attemptReset() {
        focusedField = nil
        guard password == passwordConfirm else {
            errorMessage = "Les mots de passe ne correspondent pas"
            return
        }
        guard password.count >= 6 else {
            errorMessage = "Le mot de passe doit contenir au moins 6 caractères"
            return
        }
        errorMessage = nil
        isLoading = true

        Task {
            defer { isLoading = false }
            do {
                guard let url = URL(string: baseURL) else { return }
                var request = URLRequest(url: url)
                request.httpMethod = "POST"
                request.setValue("application/json", forHTTPHeaderField: "Content-Type")
                request.timeoutInterval = 15
                request.httpBody = try? JSONSerialization.data(withJSONObject: [
                    "action":   "reset_password",
                    "token":    token,
                    "password": password
                ])
                let (data, response) = try await URLSession.shared.data(for: request)
                if let http = response as? HTTPURLResponse, http.statusCode != 200 {
                    errorMessage = apiError(from: data) ?? "Erreur \(http.statusCode)"
                    return
                }
                guard let json = try? JSONSerialization.jsonObject(with: data) as? [String: Any],
                      let success = json["success"] as? Bool, success else {
                    let msg = apiError(from: data) ?? "Erreur lors de la mise à jour"
                    errorMessage = msg
                    isTokenError = msg.lowercased().contains("lien") || msg.lowercased().contains("expir")
                    return
                }
                resetSuccess = true
            } catch {
                errorMessage = "Impossible de joindre le serveur"
            }
        }
    }

    private func apiError(from data: Data) -> String? {
        guard let json = try? JSONSerialization.jsonObject(with: data) as? [String: Any],
              let err = json["error"] as? String else { return nil }
        return err
    }
}
