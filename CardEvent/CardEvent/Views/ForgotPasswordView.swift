import SwiftUI

// MARK: - ForgotPasswordView

struct ForgotPasswordView: View {
    @Environment(\.dismiss) private var dismiss

    @State private var email       = ""
    @State private var isLoading   = false
    @State private var errorMessage: String?
    @State private var emailSent   = false
    @FocusState private var focused: Bool

    private let cyan    = Color(red: 0, green: 0.82, blue: 1)
    private let baseURL = "https://viendez.com/api/forgot-password.php"

    // MARK: - Body

    var body: some View {
        ZStack {
            Color.black.ignoresSafeArea()

            if emailSent {
                sentView
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

            Image(systemName: "lock.rotation")
                .font(.system(size: 56))
                .foregroundColor(cyan)
                .shadow(color: cyan.opacity(0.5), radius: 14)
                .padding(.bottom, 20)

            Text("Mot de passe oublié ?")
                .font(.title2.bold())
                .foregroundColor(.white)
                .padding(.bottom, 8)

            Text("Saisissez l'e-mail associé à votre compte.\nVous recevrez un lien valable 30 minutes.")
                .font(.subheadline)
                .foregroundColor(.white.opacity(0.55))
                .multilineTextAlignment(.center)
                .padding(.horizontal, 32)
                .lineSpacing(4)
                .padding(.bottom, 32)

            // Champ e-mail
            AuthTextField(
                icon:        "envelope.fill",
                placeholder: "Votre adresse e-mail",
                text:        $email
            )
            .focused($focused)
            .textInputAutocapitalization(.never)
            .autocorrectionDisabled()
            .keyboardType(.emailAddress)
            .submitLabel(.send)
            .onSubmit { attemptRequest() }
            .padding(.horizontal, 28)
            .padding(.bottom, 12)

            // Erreur
            if let error = errorMessage {
                HStack(spacing: 8) {
                    Image(systemName: "exclamationmark.triangle.fill")
                    Text(error).font(.footnote)
                }
                .foregroundColor(.orange)
                .padding(.horizontal, 28)
                .multilineTextAlignment(.center)
                .padding(.bottom, 12)
            }

            // Bouton envoyer
            Button(action: attemptRequest) {
                ZStack {
                    if isLoading {
                        ProgressView().tint(.white)
                    } else {
                        Text("Envoyer le lien")
                            .font(.headline)
                            .foregroundColor(.white)
                    }
                }
                .frame(maxWidth: .infinity)
                .frame(height: 50)
                .background(!email.isEmpty && !isLoading ? cyan : cyan.opacity(0.35))
                .clipShape(RoundedRectangle(cornerRadius: 14))
                .shadow(color: cyan.opacity(0.3), radius: 8)
            }
            .disabled(email.isEmpty || isLoading)
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

    // MARK: - Écran de confirmation

    private var sentView: some View {
        VStack(spacing: 24) {
            Spacer()

            Image(systemName: "envelope.open.fill")
                .font(.system(size: 72))
                .foregroundColor(cyan)
                .shadow(color: cyan.opacity(0.5), radius: 20)

            Text("E-mail envoyé !")
                .font(.title2.bold())
                .foregroundColor(.white)

            Text("Si un compte existe pour **\(email)**, un lien de réinitialisation a été envoyé.\n\nOuvrez l'e-mail depuis votre iPhone — l'application reprendra automatiquement.")
                .font(.subheadline)
                .foregroundColor(.white.opacity(0.7))
                .multilineTextAlignment(.center)
                .padding(.horizontal, 32)
                .lineSpacing(4)

            Spacer()

            Button(action: { dismiss() }) {
                Text("Retour à la connexion")
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

    private func attemptRequest() {
        focused = false
        guard email.contains("@") else {
            errorMessage = "Adresse e-mail invalide"
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
                    "action": "request_reset",
                    "email":  email
                ])
                let (data, response) = try await URLSession.shared.data(for: request)
                if let http = response as? HTTPURLResponse, http.statusCode != 200 {
                    let msg = apiError(from: data) ?? "Erreur \(http.statusCode)"
                    errorMessage = msg
                    return
                }
                // On affiche toujours le succès (ne pas révéler si l'email existe)
                emailSent = true
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
