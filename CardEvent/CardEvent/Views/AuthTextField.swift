import SwiftUI

// MARK: - AuthTextField
// Champ de texte stylisé partagé par LoginView et RegisterView

struct AuthTextField: View {
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
