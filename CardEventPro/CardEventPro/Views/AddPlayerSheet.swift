import SwiftUI

// MARK: - AddPlayerSheet
// Feuille permettant d'inscrire un joueur existant (recherche dans membres)
// ou de créer un nouveau joueur à la volée, avec choix public/privé.

struct AddPlayerSheet: View {

    let event: ProEvent
    var onDone: () -> Void

    @StateObject private var service = OrganizerService.shared
    @Environment(\.dismiss) private var dismiss

    // MARK: Search state
    @State private var searchText  = ""
    @State private var searchTask: Task<Void, Never>?
    @State private var results: [MemberSearchResult] = []
    @State private var isSearching = false

    // MARK: Flow
    @State private var step: AddStep = .search
    @State private var isPrivate = false
    @State private var playerVisibility: PlayerVisibility = .shared
    @State private var isSaving  = false
    @State private var errorMessage: String?
    @State private var showError = false

    enum PlayerVisibility {
        case shared   // organizers — tous les organisateurs peuvent utiliser ce joueur
        case `private` // private   — seulement moi

        var apiValue: String {
            switch self {
            case .shared:   return "organizers"
            case .private:  return "private"
            }
        }
    }

    // MARK: Create-new form
    @State private var newPseudo = ""
    @State private var newFname  = ""
    @State private var newLname  = ""
    @State private var newEmail  = ""

    private let gold = Color(red: 1.0, green: 0.75, blue: 0.0)

    // MARK: - Step enum

    enum AddStep: Equatable {
        case search
        case confirm(MemberSearchResult)
        case createNew

        static func == (lhs: AddStep, rhs: AddStep) -> Bool {
            switch (lhs, rhs) {
            case (.search,   .search):   return true
            case (.createNew, .createNew): return true
            case (.confirm(let a), .confirm(let b)): return a.id == b.id
            default: return false
            }
        }
    }

    // MARK: - Body

    var body: some View {
        NavigationStack {
            ZStack {
                Color.black.ignoresSafeArea()
                stepContent
            }
            .navigationTitle(navTitle)
            .navigationBarTitleDisplayMode(.inline)
            .toolbarColorScheme(.dark, for: .navigationBar)
            .toolbar {
                ToolbarItem(placement: .navigationBarLeading) {
                    Button(action: handleBack) {
                        if case .search = step {
                            Text("Fermer").foregroundColor(.gray)
                        } else {
                            HStack(spacing: 4) {
                                Image(systemName: "chevron.left")
                                Text("Retour")
                            }
                            .foregroundColor(gold)
                        }
                    }
                }
            }
            .alert("Erreur", isPresented: $showError) {
                Button("OK", role: .cancel) {}
            } message: {
                Text(errorMessage ?? "Une erreur est survenue")
            }
        }
    }

    // MARK: - Navigation

    private var navTitle: String {
        switch step {
        case .search:    return "Ajouter un joueur"
        case .confirm:   return "Inscrire"
        case .createNew: return "Nouveau joueur"
        }
    }

    private func handleBack() {
        if case .search = step { dismiss() }
        else { withAnimation(.easeInOut(duration: 0.2)) { step = .search } }
    }

    // MARK: - Step router

    @ViewBuilder
    private var stepContent: some View {
        switch step {
        case .search:
            searchView
        case .confirm(let member):
            confirmView(member: member)
        case .createNew:
            createView
        }
    }

    // MARK: - Step 1 : Recherche

    private var searchView: some View {
        VStack(spacing: 0) {
            // Search bar
            HStack(spacing: 10) {
                Image(systemName: "magnifyingglass")
                    .foregroundColor(.gray)
                TextField("Pseudo, prénom, nom…", text: $searchText)
                    .foregroundColor(.white)
                    .autocapitalization(.none)
                    .disableAutocorrection(true)
                    .onChange(of: searchText) { _ in scheduleSearch() }
                if isSearching {
                    ProgressView().tint(.gray).scaleEffect(0.8)
                } else if !searchText.isEmpty {
                    Button { searchText = "" } label: {
                        Image(systemName: "xmark.circle.fill").foregroundColor(.gray)
                    }
                }
            }
            .padding(.horizontal, 14)
            .padding(.vertical, 10)
            .background(Color(white: 0.15))
            .cornerRadius(12)
            .padding(.horizontal)
            .padding(.top, 12)
            .padding(.bottom, 4)

            ScrollView {
                VStack(spacing: 0) {
                    if searchText.count >= 2 && results.isEmpty && !isSearching {
                        // Aucun résultat — proposer création
                        VStack(spacing: 16) {
                            Image(systemName: "person.crop.circle.badge.questionmark")
                                .font(.system(size: 44))
                                .foregroundColor(.gray)
                            Text("Aucun résultat pour \"\(searchText)\"")
                                .font(.subheadline)
                                .foregroundColor(.gray)
                            createNewButton
                        }
                        .frame(maxWidth: .infinity)
                        .padding(.top, 48)

                    } else if searchText.count >= 2 {
                        // Résultats
                        ForEach(results) { member in
                            memberRow(member)
                        }
                        if !results.isEmpty {
                            Divider().background(Color.white.opacity(0.08))
                            createNewButton.padding(.vertical, 16)
                        }

                    } else {
                        // État initial
                        VStack(spacing: 14) {
                            Image(systemName: "person.crop.circle.badge.plus")
                                .font(.system(size: 52))
                                .foregroundColor(gold.opacity(0.35))
                            Text("Recherchez un joueur\npar pseudo, prénom ou nom")
                                .font(.subheadline)
                                .foregroundColor(.gray)
                                .multilineTextAlignment(.center)
                            createNewButton
                        }
                        .frame(maxWidth: .infinity)
                        .padding(.top, 56)
                    }
                }
                .padding(.horizontal)
            }
        }
    }

    private var createNewButton: some View {
        Button {
            withAnimation(.easeInOut(duration: 0.2)) { step = .createNew }
        } label: {
            Label("Créer un nouveau joueur", systemImage: "person.badge.plus")
                .font(.subheadline.bold())
                .foregroundColor(gold)
                .padding(.vertical, 11)
                .padding(.horizontal, 20)
                .background(gold.opacity(0.12))
                .cornerRadius(12)
        }
    }

    private func memberRow(_ member: MemberSearchResult) -> some View {
        Button {
            guard !member.isRegistered else { return }
            withAnimation(.easeInOut(duration: 0.2)) { step = .confirm(member) }
        } label: {
            HStack(spacing: 14) {
                // Avatar initiale
                ZStack {
                    Circle()
                        .fill(member.isRegistered ? Color.gray.opacity(0.15) : gold.opacity(0.15))
                        .frame(width: 46, height: 46)
                    Text(String(member.pseudo.prefix(1)).uppercased())
                        .font(.headline)
                        .foregroundColor(member.isRegistered ? .gray : gold)
                }

                VStack(alignment: .leading, spacing: 3) {
                    HStack(spacing: 5) {
                        Text(member.pseudo)
                            .font(.subheadline.bold())
                            .foregroundColor(member.isRegistered ? .gray : .white)
                        if member.proVisibility == "private" {
                            Image(systemName: "lock.fill")
                                .font(.caption2)
                                .foregroundColor(.orange)
                        } else if member.proVisibility == "organizers" {
                            Image(systemName: "person.2.fill")
                                .font(.caption2)
                                .foregroundColor(gold.opacity(0.7))
                        }
                    }
                    let name = "\(member.fname) \(member.lname)".trimmingCharacters(in: .whitespaces)
                    if !name.isEmpty {
                        Text(name)
                            .font(.caption)
                            .foregroundColor(.gray)
                    }
                }

                Spacer()

                if member.isRegistered {
                    Text("Déjà inscrit")
                        .font(.caption2.bold())
                        .foregroundColor(.orange)
                        .padding(.horizontal, 8)
                        .padding(.vertical, 3)
                        .background(Color.orange.opacity(0.18))
                        .clipShape(Capsule())
                } else {
                    Image(systemName: "chevron.right")
                        .font(.caption)
                        .foregroundColor(Color(white: 0.4))
                }
            }
            .padding(.vertical, 10)
        }
        .disabled(member.isRegistered)
        .overlay(Divider().background(Color.white.opacity(0.07)), alignment: .bottom)
    }

    // MARK: - Step 2 : Confirmation d'inscription

    private func confirmView(member: MemberSearchResult) -> some View {
        VStack(spacing: 28) {
            Spacer(minLength: 10)

            // Avatar grand format
            VStack(spacing: 10) {
                ZStack {
                    Circle()
                        .fill(gold.opacity(0.15))
                        .frame(width: 80, height: 80)
                    Text(String(member.pseudo.prefix(1)).uppercased())
                        .font(.system(size: 34, weight: .bold))
                        .foregroundColor(gold)
                }
                Text(member.pseudo)
                    .font(.title2.bold())
                    .foregroundColor(.white)
                let name = "\(member.fname) \(member.lname)".trimmingCharacters(in: .whitespaces)
                if !name.isEmpty {
                    Text(name)
                        .font(.subheadline)
                        .foregroundColor(.gray)
                }
            }

            // Toggle visibilité
            visibilityCard.padding(.horizontal)

            // Bouton inscrire
            Button {
                Task { await performRegister(memberId: member.id) }
            } label: {
                Group {
                    if isSaving {
                        ProgressView().tint(.black)
                    } else {
                        Label("Inscrire à \"\(event.titre)\"", systemImage: "person.badge.plus")
                            .font(.headline)
                            .lineLimit(1)
                    }
                }
                .frame(maxWidth: .infinity)
                .padding()
                .background(gold)
                .foregroundColor(.black)
                .cornerRadius(14)
            }
            .disabled(isSaving)
            .padding(.horizontal)

            Spacer()
        }
    }

    // MARK: - Step 3 : Création d'un nouveau joueur

    private var createView: some View {
        ScrollView {
            VStack(spacing: 20) {
                VStack(spacing: 1) {
                    createField("Pseudo *",    text: $newPseudo, placeholder: "ex: JohnPoker")
                    createField("Prénom",      text: $newFname,  placeholder: "optionnel")
                    createField("Nom",         text: $newLname,  placeholder: "optionnel")
                    createField("Email",       text: $newEmail,  placeholder: "optionnel",
                                keyboardType: .emailAddress)
                }
                .background(Color(white: 0.1))
                .cornerRadius(14)
                .padding(.horizontal)
                .padding(.top, 8)

                playerVisibilityCard.padding(.horizontal)

                visibilityCard.padding(.horizontal)

                Text("Le joueur pourra se connecter avec ce pseudo et réinitialiser son mot de passe plus tard.")
                    .font(.caption)
                    .foregroundColor(.gray)
                    .multilineTextAlignment(.center)
                    .padding(.horizontal, 28)

                Button {
                    Task { await performCreateAndRegister() }
                } label: {
                    Group {
                        if isSaving {
                            ProgressView().tint(.black)
                        } else {
                            Label("Créer et inscrire", systemImage: "person.badge.plus")
                                .font(.headline)
                        }
                    }
                    .frame(maxWidth: .infinity)
                    .padding()
                    .background(newPseudo.count >= 2 ? gold : Color(white: 0.3))
                    .foregroundColor(newPseudo.count >= 2 ? .black : Color(white: 0.6))
                    .cornerRadius(14)
                }
                .disabled(newPseudo.count < 2 || isSaving)
                .padding(.horizontal)

                Spacer(minLength: 40)
            }
        }
    }

    // MARK: - Shared UI

    private func createField(_ label: String, text: Binding<String>, placeholder: String,
                              keyboardType: UIKeyboardType = .default) -> some View {
        HStack(alignment: .center, spacing: 10) {
            Text(label)
                .font(.subheadline)
                .foregroundColor(.gray)
                .frame(width: 80, alignment: .leading)
            TextField(placeholder, text: text)
                .foregroundColor(.white)
                .autocorrectionDisabled()
                .textInputAutocapitalization(keyboardType == .emailAddress ? .never : .words)
                .keyboardType(keyboardType)
        }
        .padding(.horizontal, 16)
        .padding(.vertical, 13)
        .overlay(Divider().background(Color.white.opacity(0.07)), alignment: .bottom)
    }

    private var visibilityCard: some View {
        HStack(spacing: 14) {
            Image(systemName: isPrivate ? "lock.fill" : "globe")
                .font(.title3)
                .foregroundColor(isPrivate ? .orange : gold)
                .frame(width: 28)

            VStack(alignment: .leading, spacing: 3) {
                Text("Inscription privée")
                    .font(.subheadline.bold())
                    .foregroundColor(.white)
                Text(isPrivate
                    ? "Visible uniquement par vous"
                    : "Visible dans la liste publique")
                    .font(.caption)
                    .foregroundColor(.gray)
            }

            Spacer()
            Toggle("", isOn: $isPrivate)
                .tint(gold)
                .labelsHidden()
        }
        .padding()
        .background(Color(white: 0.1))
        .cornerRadius(14)
    }

    private var playerVisibilityCard: some View {
        VStack(spacing: 0) {
            Text("Visibilité de ce joueur")
                .font(.caption.bold())
                .foregroundColor(.gray)
                .frame(maxWidth: .infinity, alignment: .leading)
                .padding(.horizontal, 16)
                .padding(.top, 12)
                .padding(.bottom, 6)

            HStack(spacing: 0) {
                visibilityOption(
                    label: "Partagé",
                    subtitle: "Tous les organisateurs",
                    icon: "person.2.fill",
                    color: gold,
                    isSelected: playerVisibility == .shared
                ) { playerVisibility = .shared }

                Divider().frame(height: 56)

                visibilityOption(
                    label: "Privé",
                    subtitle: "Moi seulement",
                    icon: "lock.fill",
                    color: .orange,
                    isSelected: playerVisibility == .private
                ) { playerVisibility = .private }
            }
            .background(Color(white: 0.12))
            .cornerRadius(12)
            .padding(.horizontal, 0)
            .padding(.bottom, 12)
        }
        .background(Color(white: 0.1))
        .cornerRadius(14)
    }

    private func visibilityOption(label: String, subtitle: String, icon: String,
                                   color: Color, isSelected: Bool, action: @escaping () -> Void) -> some View {
        Button(action: action) {
            VStack(spacing: 4) {
                Image(systemName: icon)
                    .font(.title3)
                    .foregroundColor(isSelected ? color : Color(white: 0.45))
                Text(label)
                    .font(.subheadline.bold())
                    .foregroundColor(isSelected ? .white : Color(white: 0.45))
                Text(subtitle)
                    .font(.caption2)
                    .foregroundColor(isSelected ? color.opacity(0.8) : Color(white: 0.35))
            }
            .frame(maxWidth: .infinity)
            .padding(.vertical, 12)
            .background(isSelected ? color.opacity(0.15) : Color.clear)
        }
    }

    // MARK: - Logic

    private func scheduleSearch() {
        searchTask?.cancel()
        results = []
        guard searchText.count >= 2 else { return }
        let query = searchText
        searchTask = Task { @MainActor in
            try? await Task.sleep(nanoseconds: 320_000_000)
            guard !Task.isCancelled else { return }
            isSearching = true
            results = await service.searchMembers(query: query, eventId: event.id)
            isSearching = false
        }
    }

    private func performRegister(memberId: Int) async {
        isSaving = true
        defer { isSaving = false }
        let ok = await service.registerPlayerWithVisibility(
            eventId: event.id, memberId: memberId, isPrivate: isPrivate)
        if ok {
            onDone()
            dismiss()
        } else {
            errorMessage = service.errorMessage ?? "Impossible d'inscrire ce joueur"
            showError = true
        }
    }

    private func performCreateAndRegister() async {
        isSaving = true
        defer { isSaving = false }

        guard let created = await service.createMember(
            pseudo: newPseudo.trimmingCharacters(in: .whitespaces),
            fname:  newFname.trimmingCharacters(in: .whitespaces),
            lname:  newLname.trimmingCharacters(in: .whitespaces),
            email:  newEmail.trimmingCharacters(in: .whitespaces),
            visibility: playerVisibility.apiValue
        ) else {
            errorMessage = "Impossible de créer le joueur (erreur réseau)"
            showError = true
            return
        }

        guard created.success, let memberId = created.memberId else {
            errorMessage = created.message ?? "Impossible de créer le joueur"
            showError = true
            return
        }

        let ok = await service.registerPlayerWithVisibility(
            eventId: event.id, memberId: memberId, isPrivate: isPrivate)
        if ok {
            onDone()
            dismiss()
        } else {
            errorMessage = service.errorMessage ?? "Joueur créé mais inscription échouée (ID: \(memberId))"
            showError = true
        }
    }
}
