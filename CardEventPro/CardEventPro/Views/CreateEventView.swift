import SwiftUI

// MARK: - CreateEventView
// Formulaire pour créer ou modifier une partie Pro

enum EventFormMode {
    case create
    case edit(ProEvent)
}

struct CreateEventView: View {

    let mode: EventFormMode
    var onDone: (ProEvent?) -> Void

    @StateObject private var service = OrganizerService.shared
    @Environment(\.dismiss) private var dismiss

    @State private var form = NewEventForm()
    @State private var isSaving = false
    @State private var errorMessage: String?
    @State private var showError = false

    private let gold   = Color(red: 1.0, green: 0.75, blue: 0.0)
    private let devises = ["EUR", "USD", "GBP", "CHF", "CAD"]

    var isEditing: Bool {
        if case .edit = mode { return true }
        return false
    }

    var navigationTitle: String {
        isEditing ? "Modifier la partie" : "Nouvelle partie"
    }

    var body: some View {
        NavigationStack {
            ZStack {
                Color.black.ignoresSafeArea()

                ScrollView {
                    VStack(spacing: 0) {
                        sectionHeader("Informations générales", icon: "info.circle")
                        generalSection

                        sectionHeader("Lieu & Date", icon: "calendar")
                        locationDateSection

                        sectionHeader("Paramètres financiers", icon: "eurosign.circle")
                        financialSection

                        sectionHeader("Structure & Blindes", icon: "chart.bar.fill")
                        pokerSection

                        sectionHeader("Visibilité", icon: "eye")
                        visibilitySection

                        Spacer(minLength: 40)
                    }
                    .padding(.top, 8)
                }
            }
            .navigationTitle(navigationTitle)
            .navigationBarTitleDisplayMode(.inline)
            .toolbarColorScheme(.dark, for: .navigationBar)
            .toolbar {
                ToolbarItem(placement: .navigationBarLeading) {
                    Button("Annuler") { dismiss() }
                        .foregroundColor(.gray)
                }
                ToolbarItem(placement: .navigationBarTrailing) {
                    Button {
                        Task { await save() }
                    } label: {
                        if isSaving {
                            ProgressView().tint(gold)
                        } else {
                            Text(isEditing ? "Enregistrer" : "Créer")
                                .bold()
                                .foregroundColor(form.isValid ? gold : .gray)
                        }
                    }
                    .disabled(!form.isValid || isSaving)
                }
            }
            .onAppear { prefillForm() }
            .alert("Erreur", isPresented: $showError) {
                Button("OK", role: .cancel) {}
            } message: {
                Text(errorMessage ?? "")
            }
        }
    }

    // MARK: - Sections

    private var generalSection: some View {
        VStack(spacing: 1) {
            ProFormRow {
                VStack(alignment: .leading, spacing: 4) {
                    Text("Titre *").proLabel()
                    TextField("Ex: Tournoi du vendredi soir", text: $form.titre)
                        .proTextField()
                }
            }
            ProFormRow {
                VStack(alignment: .leading, spacing: 4) {
                    Text("Description").proLabel()
                    TextField("Décrivez votre partie…", text: $form.description, axis: .vertical)
                        .proTextField()
                        .lineLimit(3...6)
                }
            }
        }
        .padding(.bottom, 8)
    }

    private var locationDateSection: some View {
        VStack(spacing: 1) {
            ProFormRow {
                VStack(alignment: .leading, spacing: 4) {
                    Text("Lieu *").proLabel()
                    TextField("Adresse ou nom du lieu", text: $form.lieu)
                        .proTextField()
                }
            }
            ProFormRow {
                DatePicker(
                    "Date et heure",
                    selection: $form.dateEvent,
                    in: Date()...,
                    displayedComponents: [.date, .hourAndMinute]
                )
                .foregroundColor(.white)
                .tint(gold)
                .environment(\.locale, Locale(identifier: "fr_FR"))
            }
        }
        .padding(.bottom, 8)
    }

    private var financialSection: some View {
        VStack(spacing: 1) {
            ProFormRow {
                HStack {
                    Text("Buy-in").foregroundColor(.white)
                    Spacer()
                    HStack(spacing: 4) {
                        TextField("0", value: $form.buyIn, format: .number)
                            .keyboardType(.decimalPad)
                            .multilineTextAlignment(.trailing)
                            .foregroundColor(gold)
                            .frame(width: 70)
                        Picker("", selection: $form.devise) {
                            ForEach(devises, id: \.self) { d in
                                Text(d).tag(d)
                            }
                        }
                        .pickerStyle(.menu)
                        .foregroundColor(gold)
                        .tint(gold)
                    }
                }
            }
            ProFormRow {
                HStack {
                    Text("Nombre de joueurs max").foregroundColor(.white)
                    Spacer()
                    HStack(spacing: 8) {
                        Button {
                            if form.maxJoueurs > 2 { form.maxJoueurs -= 1 }
                        } label: {
                            Image(systemName: "minus.circle.fill")
                                .font(.title3)
                                .foregroundColor(form.maxJoueurs > 2 ? gold : .gray)
                        }
                        Text("\(form.maxJoueurs)")
                            .font(.headline.monospacedDigit())
                            .foregroundColor(gold)
                            .frame(width: 36, alignment: .center)
                        Button {
                            if form.maxJoueurs < 500 { form.maxJoueurs += 1 }
                        } label: {
                            Image(systemName: "plus.circle.fill")
                                .font(.title3)
                                .foregroundColor(gold)
                        }
                    }
                }
            }
        }
        .padding(.bottom, 8)
    }

    // MARK: - Section Poker

    private var pokerSection: some View {
        VStack(spacing: 1) {
            ProFormRow {
                VStack(alignment: .leading, spacing: 4) {
                    Text("Structure").foregroundColor(.white)
                    Picker("Structure", selection: $form.structureId) {
                        Text("Semaine").tag(1 as Int)
                        Text("Week-end").tag(5 as Int)
                    }
                    .pickerStyle(.segmented)
                }
            }
            proStepper(label: "Rake", suffix: "e", value: $form.rake, range: 0...25)
            proStepper(label: "Bounty", suffix: "e", value: $form.bounty, range: 0...10)
            ProFormRow {
                HStack {
                    Text("Jetons de depart").foregroundColor(.white)
                    Spacer()
                    TextField("35000", value: $form.jetons, format: .number)
                        .multilineTextAlignment(.trailing)
                        .foregroundColor(gold)
                        .frame(width: 90)
                }
            }
            proStepper(label: "Nb recaves", suffix: "", value: $form.nbRecaves, range: 0...10)
            ProFormRow {
                HStack {
                    Text("Montant recave").foregroundColor(.white)
                    Spacer()
                    TextField("10", value: $form.recaveMontant, format: .number)
                        .multilineTextAlignment(.trailing)
                        .foregroundColor(gold)
                        .frame(width: 80)
                    Text("e").foregroundColor(.gray)
                }
            }
            ProFormRow {
                HStack {
                    Text("Jetons par recave").foregroundColor(.white)
                    Spacer()
                    TextField("40000", value: $form.recaveJetons, format: .number)
                        .multilineTextAlignment(.trailing)
                        .foregroundColor(gold)
                        .frame(width: 90)
                }
            }
            ProFormRow {
                Toggle(isOn: Binding(
                    get: { form.bonus > 0 },
                    set: { form.bonus = $0 ? 5000 : 0 }
                )) {
                    VStack(alignment: .leading, spacing: 2) {
                        Text("Bonus").foregroundColor(.white)
                        Text(form.bonus > 0 ? "5 000 jetons" : "Pas de bonus")
                            .font(.caption)
                            .foregroundColor(.gray)
                    }
                }
                .tint(gold)
            }
            proStepper(label: "Nombre de tables", suffix: "", value: $form.nbTables, range: 1...20)
        }
        .padding(.bottom, 8)
    }

    @ViewBuilder
    private func proStepper(label: String, suffix: String, value: Binding<Int>,
                            range: ClosedRange<Int>, step: Int = 1) -> some View {
        ProFormRow {
            HStack {
                Text(label).foregroundColor(.white)
                Spacer()
                HStack(spacing: 8) {
                    Button {
                        if value.wrappedValue > range.lowerBound {
                            value.wrappedValue -= step
                        }
                    } label: {
                        Image(systemName: "minus.circle.fill")
                            .font(.title3)
                            .foregroundColor(value.wrappedValue > range.lowerBound ? gold : .gray)
                    }
                    Text(suffix.isEmpty ? "\(value.wrappedValue)" : "\(value.wrappedValue)\(suffix)")
                        .font(.headline.monospacedDigit())
                        .foregroundColor(gold)
                        .frame(minWidth: 44, alignment: .center)
                    Button {
                        if value.wrappedValue < range.upperBound {
                            value.wrappedValue += step
                        }
                    } label: {
                        Image(systemName: "plus.circle.fill")
                            .font(.title3)
                            .foregroundColor(gold)
                    }
                }
            }
        }
    }

    private var visibilitySection: some View {
        ProFormRow {
            Toggle(isOn: $form.isPublic) {
                VStack(alignment: .leading, spacing: 2) {
                    Text("Partie publique").foregroundColor(.white)
                    Text(form.isPublic ? "Visible par tous les joueurs" : "Accessible sur invitation")
                        .font(.caption)
                        .foregroundColor(.gray)
                }
            }
            .tint(gold)
        }
    }

    // MARK: - Helpers visuels

    private func sectionHeader(_ title: String, icon: String) -> some View {
        HStack(spacing: 6) {
            Image(systemName: icon)
                .font(.caption)
                .foregroundColor(gold)
            Text(title.uppercased())
                .font(.caption.bold())
                .foregroundColor(.gray)
            Spacer()
        }
        .padding(.horizontal, 16)
        .padding(.top, 20)
        .padding(.bottom, 6)
    }

    // MARK: - Logic

    private func prefillForm() {
        if case .edit(let event) = mode {
            form.titre       = event.titre
            form.description = event.description
            form.lieu        = event.lieu
            form.maxJoueurs  = event.maxJoueurs
            form.buyIn       = event.buyIn
            form.devise      = event.devise
            form.isPublic    = event.isPublic

            form.structureId   = event.structureId
            form.rake          = event.rake
            form.bounty        = event.bounty
            form.jetons        = event.jetons
            form.nbRecaves     = event.nbRecaves
            form.recaveMontant = event.recaveMontant
            form.recaveJetons  = event.recaveJetons
            form.bonus         = event.bonus
            form.nbTables      = event.nbTables

            let fmtIn = DateFormatter()
            fmtIn.dateFormat = "yyyy-MM-dd HH:mm:ss"
            if let d = fmtIn.date(from: event.dateEvent) {
                form.dateEvent = d
            }
        }
    }

    private func save() async {
        isSaving = true
        defer { isSaving = false }

        let result: Result<ProEvent, Error>
        switch mode {
        case .create:
            result = await service.createEvent(form: form)
        case .edit(let event):
            result = await service.updateEvent(id: event.id, form: form)
        }

        switch result {
        case .success(let event):
            onDone(event)
            dismiss()
        case .failure(let error):
            errorMessage = error.localizedDescription
            showError = true
        }
    }
}

// MARK: - Composants réutilisables

struct ProFormRow<Content: View>: View {
    let content: Content
    init(@ViewBuilder content: () -> Content) { self.content = content() }

    var body: some View {
        content
            .padding()
            .background(Color(white: 0.12))
            .overlay(Divider().tint(Color.white.opacity(0.08)), alignment: .bottom)
    }
}

extension Text {
    func proLabel() -> some View {
        self.font(.caption).foregroundColor(.gray)
    }
}

extension TextField {
    func proTextField() -> some View {
        self
            .foregroundColor(.white)
            .tint(Color(red: 1.0, green: 0.75, blue: 0.0))
    }
}
