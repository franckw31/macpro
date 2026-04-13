import SwiftUI

// MARK: - LoginView

struct LoginView: View {
    @ObservedObject var auth: AuthService

    @State private var username = ""
    @State private var password = ""
    @State private var showRegister = false
    @FocusState private var focusedField: Field?
    @Environment(\.verticalSizeClass) private var vSizeClass

    private enum Field { case username, password }

    private let cyan = Color(red: 0, green: 0.82, blue: 1)

    // Paramètres adaptatifs selon l'espace vertical
    private var isCompact: Bool { vSizeClass == .compact }
    private var outerSpacing: CGFloat { isCompact ? 12 : 28 }
    private var iconSize: CGFloat    { isCompact ? 32 : 54 }
    private var titleSize: CGFloat   { isCompact ? 24 : 34 }

    var body: some View {
        ZStack {
            // ── Fond ─────────────────────────────────────────────
            Color.black.ignoresSafeArea()

            // Background image removed

            // ── Contenu ───────────────────────────────────────────
            ScrollView {
                VStack(spacing: outerSpacing) {

                    // Logo / titre (masqué si très peu de place)
                    if !isCompact {
                        VStack(spacing: 8) {
                            Image(systemName: "suit.spade.fill")
                                .font(.system(size: iconSize))
                                .foregroundColor(cyan)
                                .shadow(color: cyan.opacity(0.6), radius: 12)

                            Text("CardEvent")
                                .font(.system(size: titleSize, weight: .black, design: .rounded))
                                .foregroundColor(.white)

                            Text("Connectez-vous pour continuer")
                                .font(.subheadline)
                                .foregroundColor(.white.opacity(0.6))
                        }
                        .padding(.top, 48)
                    } else {
                        // Vue compacte (paysage) : juste le titre
                        Text("CardEvent")
                            .font(.system(size: titleSize, weight: .black, design: .rounded))
                            .foregroundColor(cyan)
                            .padding(.top, 16)
                    }

                    // Formulaire
                    VStack(spacing: 14) {
                        AuthTextField(
                            icon:        "person.fill",
                            placeholder: "Pseudo ou e-mail",
                            text:        $username
                        )
                        .focused($focusedField, equals: .username)
                        .textInputAutocapitalization(.never)
                        .autocorrectionDisabled()
                        .submitLabel(.next)
                        .onSubmit { focusedField = .password }

                        AuthTextField(
                            icon:        "lock.fill",
                            placeholder: "Mot de passe",
                            text:        $password,
                            isSecure:    true
                        )
                        .focused($focusedField, equals: .password)
                        .submitLabel(.go)
                        .onSubmit { attemptLogin() }
                    }
                    .padding(.horizontal, 28)

                    // Message d'erreur
                    if let error = auth.errorMessage {
                        HStack(spacing: 8) {
                            Image(systemName: "exclamationmark.triangle.fill")
                            Text(error)
                                .font(.footnote)
                        }
                        .foregroundColor(.orange)
                        .padding(.horizontal, 28)
                        .multilineTextAlignment(.center)
                    }

                    // Bouton
                    Button(action: attemptLogin) {
                        ZStack {
                            if auth.isLoading {
                                ProgressView()
                                    .tint(.white)
                            } else {
                                Text("Se connecter")
                                    .font(.headline)
                                    .foregroundColor(.white)
                            }
                        }
                        .frame(maxWidth: .infinity)
                        .frame(height: 50)
                        .background(auth.isLoading || username.isEmpty || password.isEmpty
                                    ? cyan.opacity(0.4) : cyan)
                        .clipShape(RoundedRectangle(cornerRadius: 14))
                        .shadow(color: cyan.opacity(0.3), radius: 8)
                    }
                    .disabled(auth.isLoading || username.isEmpty || password.isEmpty)
                    .padding(.horizontal, 28)

                    // Bouton créer un compte
                    Button(action: { showRegister = true }) {
                        Text("Nouveau joueur ? Créer un compte")
                            .font(.footnote)
                            .foregroundColor(.white.opacity(0.5))
                            .padding(.vertical, 8)
                    }
                    .padding(.bottom, isCompact ? 16 : 40)
                }
                .frame(maxWidth: 480) // centré sur iPad/paysage
                .frame(maxWidth: .infinity)
            }
            .scrollDismissesKeyboard(.interactively)
        }
        .sheet(isPresented: $showRegister) {
            RegisterView()
        }
    }

    private func attemptLogin() {
        focusedField = nil
        Task { await auth.login(username: username, password: password) }
    }
}

// MARK: - AuthTextField

private struct AuthTextField: View {
    let icon: String
    let placeholder: String
    @Binding var text: String
    var isSecure: Bool = false

    private let cyan = Color(red: 0, green: 0.82, blue: 1)

    var body: some View {
        HStack(spacing: 12) {
            Image(systemName: icon)
                .foregroundColor(cyan)
                .frame(width: 20)

            if isSecure {
                SecureField(placeholder, text: $text)
                    .foregroundColor(.white)
                    .tint(cyan)
            } else {
                TextField(placeholder, text: $text)
                    .foregroundColor(.white)
                    .tint(cyan)
            }
        }
        .padding(14)
        .background(Color.white.opacity(0.08))
        .clipShape(RoundedRectangle(cornerRadius: 12))
        .overlay(
            RoundedRectangle(cornerRadius: 12)
                .stroke(Color.white.opacity(0.15), lineWidth: 1)
        )
    }
}
