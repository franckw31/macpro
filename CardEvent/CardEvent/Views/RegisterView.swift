import SwiftUI

// MARK: - RegisterView

struct RegisterView: View {
    @Environment(\.dismiss) private var dismiss

    // ── Champs du formulaire ─────────────────────────────────────
    @State private var pseudo          = ""
    @State private var lname           = ""
    @State private var fname           = ""
    @State private var email           = ""
    @State private var emailConfirm    = ""
    @State private var password        = ""
    @State private var passwordConfirm = ""
    @State private var ville           = ""
    @State private var dateNaissance   = Calendar.current.date(byAdding: .year, value: -18, to: Date()) ?? Date()

    // ── État UI ──────────────────────────────────────────────────
    @State private var isLoading           = false
    @State private var errorMessage: String?
    @State private var registrationSuccess  = false
    @State private var accountVerified      = false
    @FocusState private var focusedField: Field?

    private enum Field: Hashable {
        case pseudo, lname, fname, email, emailConfirm
        case password, passwordConfirm, ville
    }

    private let cyan    = Color(red: 0, green: 0.82, blue: 1)
    private let baseURL = "https://viendez.com/api/register-player.php"

    // Limite d'âge minimum (6 ans)
    private var maxDate: Date {
        Calendar.current.date(byAdding: .year, value: -6, to: Date()) ?? Date()
    }

    private var isFormValid: Bool {
        !pseudo.isEmpty && !lname.isEmpty && !fname.isEmpty &&
        !email.isEmpty && email == emailConfirm && email.contains("@") &&
        !password.isEmpty && password == passwordConfirm && password.count >= 6 &&
        !ville.isEmpty
    }

    // MARK: - Body

    var body: some View {
        ZStack {
            Color.black.ignoresSafeArea()

            if registrationSuccess {
                successView
            } else {
                formScrollView
            }
        }
        .preferredColorScheme(.dark)
        .onReceive(NotificationCenter.default.publisher(for: .cardEventEmailVerified)) { _ in
            accountVerified     = true
            registrationSuccess = true
        }

    // MARK: - Écran de succès

    private var successView: some View {
        VStack(spacing: 24) {
            Spacer()

            Image(systemName: accountVerified
                  ? "checkmark.seal.fill"
                  : "envelope.badge.checkmark.fill")
                .font(.system(size: 72))
                .foregroundColor(accountVerified ? .green : cyan)
                .shadow(color: (accountVerified ? Color.green : cyan).opacity(0.5), radius: 20)
                .animation(.easeInOut, value: accountVerified)

            Text(accountVerified ? "Compte activé !" : "Vérifiez votre e-mail !")
                .font(.title2.bold())
                .foregroundColor(.white)
                .animation(.easeInOut, value: accountVerified)

            if accountVerified {
                Text("Votre compte est maintenant actif.\nVous pouvez vous connecter.")
                    .font(.subheadline)
                    .foregroundColor(.white.opacity(0.7))
                    .multilineTextAlignment(.center)
                    .padding(.horizontal, 32)
                    .lineSpacing(4)
            } else {
                Text("Un lien d’activation a été envoyé à :\n**\(email)**\n\nCliquez sur ce lien depuis votre téléphone — l’application reprendra automatiquement.")
                    .font(.subheadline)
                    .foregroundColor(.white.opacity(0.7))
                    .multilineTextAlignment(.center)
                    .padding(.horizontal, 32)
                    .lineSpacing(4)
            }

            Spacer()

            Button(action: { dismiss() }) {
                Text(accountVerified ? "Se connecter" : "Retour à la connexion")
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

    // MARK: - Formulaire

    private var formScrollView: some View {
        ScrollView {
            VStack(spacing: 0) {

                // ── En-tête ──────────────────────────────────────
                VStack(spacing: 6) {
                    Image(systemName: "person.badge.plus.fill")
                        .font(.system(size: 48))
                        .foregroundColor(cyan)
                        .shadow(color: cyan.opacity(0.5), radius: 12)
                        .padding(.top, 36)

                    Text("Nouveau joueur")
                        .font(.title2.bold())
                        .foregroundColor(.white)

                    Text("Tous les champs sont obligatoires")
                        .font(.caption)
                        .foregroundColor(.white.opacity(0.45))
                }
                .padding(.bottom, 28)

                // ── Section Identité ─────────────────────────────
                sectionLabel("Identité")

                VStack(spacing: 12) {
                    AuthTextField(icon: "person.text.rectangle.fill",
                                  placeholder: "Pseudo *",
                                  text: $pseudo)
                        .focused($focusedField, equals: .pseudo)
                        .textInputAutocapitalization(.never)
                        .autocorrectionDisabled()

                    AuthTextField(icon: "person.fill",
                                  placeholder: "Prénom *",
                                  text: $fname)
                        .focused($focusedField, equals: .fname)
                        .textInputAutocapitalization(.words)

                    AuthTextField(icon: "person.fill",
                                  placeholder: "Nom *",
                                  text: $lname)
                        .focused($focusedField, equals: .lname)
                        .textInputAutocapitalization(.words)

                    // Date de naissance
                    datePickerRow

                    AuthTextField(icon: "mappin.and.ellipse",
                                  placeholder: "Ville *",
                                  text: $ville)
                        .focused($focusedField, equals: .ville)
                        .textInputAutocapitalization(.words)
                }
                .padding(.horizontal, 28)
                .padding(.bottom, 24)

                // ── Section Compte ───────────────────────────────
                sectionLabel("Compte")

                VStack(spacing: 12) {
                    // E-mail
                    AuthTextField(icon: "envelope.fill",
                                  placeholder: "E-mail *",
                                  text: $email)
                        .focused($focusedField, equals: .email)
                        .textInputAutocapitalization(.never)
                        .autocorrectionDisabled()
                        .keyboardType(.emailAddress)

                    // Confirmation e-mail
                    HStack(spacing: 8) {
                        AuthTextField(icon: "envelope.badge.fill",
                                      placeholder: "Confirmer l'e-mail *",
                                      text: $emailConfirm)
                            .focused($focusedField, equals: .emailConfirm)
                            .textInputAutocapitalization(.never)
                            .autocorrectionDisabled()
                            .keyboardType(.emailAddress)

                        validationIcon(ok: email == emailConfirm, visible: !emailConfirm.isEmpty)
                    }

                    // Mot de passe
                    AuthTextField(icon: "lock.fill",
                                  placeholder: "Mot de passe * (6 car. min.)",
                                  text: $password,
                                  isSecure: true)
                        .focused($focusedField, equals: .password)

                    // Confirmation mot de passe
                    HStack(spacing: 8) {
                        AuthTextField(icon: "lock.badge.checkmark.fill",
                                      placeholder: "Confirmer le mot de passe *",
                                      text: $passwordConfirm,
                                      isSecure: true)
                            .focused($focusedField, equals: .passwordConfirm)

                        validationIcon(ok: password == passwordConfirm, visible: !passwordConfirm.isEmpty)
                    }
                }
                .padding(.horizontal, 28)
                .padding(.bottom, 20)

                // ── Message d'erreur ─────────────────────────────
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

                // ── Bouton de validation ─────────────────────────
                Button(action: attemptRegister) {
                    ZStack {
                        if isLoading {
                            ProgressView().tint(.white)
                        } else {
                            Text("Créer mon compte")
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

                // ── Retour connexion ─────────────────────────────
                Button(action: { dismiss() }) {
                    Text("Déjà un compte ? Se connecter")
                        .font(.footnote)
                        .foregroundColor(.white.opacity(0.5))
                        .padding(.vertical, 16)
                }
                .padding(.bottom, 16)
            }
            .frame(maxWidth: 480)
            .frame(maxWidth: .infinity)
        }
        .scrollDismissesKeyboard(.interactively)
    }

    // MARK: - Sous-vues

    @ViewBuilder
    private func sectionLabel(_ title: String) -> some View {
        HStack {
            Text(title.uppercased())
                .font(.caption.bold())
                .foregroundColor(cyan.opacity(0.8))
                .padding(.leading, 32)
            Spacer()
        }
        .padding(.bottom, 8)
    }

    @ViewBuilder
    private func validationIcon(ok: Bool, visible: Bool) -> some View {
        if visible {
            Image(systemName: ok ? "checkmark.circle.fill" : "xmark.circle.fill")
                .foregroundColor(ok ? .green : .red)
                .font(.title3)
        }
    }

    private var datePickerRow: some View {
        HStack(spacing: 12) {
            Image(systemName: "calendar")
                .foregroundColor(cyan)
                .frame(width: 20)

            Text("Date de naissance")
                .foregroundColor(.white.opacity(0.5))
                .font(.body)

            Spacer()

            DatePicker(
                "",
                selection: $dateNaissance,
                in: ...maxDate,
                displayedComponents: .date
            )
            .datePickerStyle(.compact)
            .labelsHidden()
            .tint(cyan)
            .colorScheme(.dark)
        }
        .padding(14)
        .background(Color.white.opacity(0.08))
        .clipShape(RoundedRectangle(cornerRadius: 12))
        .overlay(
            RoundedRectangle(cornerRadius: 12)
                .stroke(Color.white.opacity(0.15), lineWidth: 1)
        )
    }

    // MARK: - Action

    private func attemptRegister() {
        focusedField = nil
        errorMessage = nil

        // Validations supplémentaires
        guard email.contains("@") else {
            errorMessage = "Adresse e-mail invalide"
            return
        }
        guard email == emailConfirm else {
            errorMessage = "Les adresses e-mail ne correspondent pas"
            return
        }
        guard password.count >= 6 else {
            errorMessage = "Le mot de passe doit contenir au moins 6 caractères"
            return
        }
        guard password == passwordConfirm else {
            errorMessage = "Les mots de passe ne correspondent pas"
            return
        }

        isLoading = true

        let formatter = DateFormatter()
        formatter.dateFormat = "yyyy-MM-dd"
        let dateStr = formatter.string(from: dateNaissance)

        Task {
            defer { isLoading = false }
            do {
                guard let url = URL(string: baseURL) else { return }
                var request = URLRequest(url: url)
                request.httpMethod = "POST"
                request.setValue("application/json", forHTTPHeaderField: "Content-Type")
                request.timeoutInterval = 15

                let payload: [String: Any] = [
                    "action":          "register",
                    "pseudo":          pseudo,
                    "lname":           lname,
                    "fname":           fname,
                    "email":           email,
                    "password":        password,
                    "ville":           ville,
                    "date_naissance":  dateStr
                ]
                request.httpBody = try? JSONSerialization.data(withJSONObject: payload)

                let (data, response) = try await URLSession.shared.data(for: request)

                if let http = response as? HTTPURLResponse, http.statusCode != 200 {
                    errorMessage = apiError(from: data) ?? "Erreur \(http.statusCode)"
                    return
                }

                guard let json = try? JSONSerialization.jsonObject(with: data) as? [String: Any],
                      let success = json["success"] as? Bool else {
                    errorMessage = "Réponse inattendue du serveur"
                    return
                }

                if success {
                    registrationSuccess = true
                } else {
                    errorMessage = json["error"] as? String ?? "Erreur lors de la création du compte"
                }
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
