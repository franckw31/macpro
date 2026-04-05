import SwiftUI

// MARK: - Helper Views
private struct TimerDisplay: View {
    let time: String
    let size: CGSize
    
    private var fontSize: CGFloat {
        let base = min(size.width, size.height)
        if base < 350 { return 52 }
        if base < 430 { return 64 }
        if base < 700 { return 76 }
        return 96
    }
    
    var body: some View {
        Text(time)
            .font(.system(size: fontSize, weight: .bold, design: .rounded))
            .foregroundStyle(.red)
            .monospacedDigit()
            .minimumScaleFactor(0.8)
            .lineLimit(1)
    }
}

private struct BlindInfo: View {
    let level: BlindLevel
    let nextBlindText: String
    let size: CGSize
    
    private var fontSize: CGFloat {
        let base = min(size.width, size.height)
        if base < 350 { return 24 }
        if base < 430 { return 28 }
        if base < 700 { return 34 }
        return 38
    }
    
    var body: some View {
        VStack(spacing: 8) {
            Text("Niveau \(level.level)")
                .font(.title2.bold())
            Text("\(level.smallBlind)/\(level.bigBlind)")
                .font(.system(size: fontSize, weight: .bold, design: .rounded))
                .foregroundStyle(.yellow)
                .minimumScaleFactor(0.8)
            Text("Ante: \(level.ante)")
                .font(.headline)
                .foregroundStyle(.secondary)
            Text("Prochain: \(nextBlindText)")
                .font(.headline)
                .foregroundStyle(.blue)
                .multilineTextAlignment(.center)
        }
    }
}

private struct PlayerActivityInfo: View {
    let playerCount: Int
    let nextActivityDate: String?
    let size: CGSize
    let isCompact: Bool
    let onDecrement: () -> Void
    let onIncrement: () -> Void
    
    private var blindFontSize: CGFloat {
        let base = min(size.width, size.height)
        if base < 350 { return 24 }
        if base < 430 { return 28 }
        if base < 700 { return 34 }
        return 38
    }
    
    private var activityFontSize: CGFloat {
        let base = min(size.width, size.height)
        if base < 350 { return 20 }
        if base < 430 { return 24 }
        if base < 700 { return 28 }
        return 32
    }
    
    var body: some View {
        if isCompact {
            VStack(spacing: 14) {
                HStack(spacing: 12) {
                    Button("-", action: onDecrement)
                        .buttonStyle(.bordered)
                        .disabled(playerCount <= 2)
                    
                    VStack(spacing: 4) {
                        Text("Joueurs")
                            .font(.caption)
                            .foregroundColor(.white)
                        Text("\(playerCount)")
                            .font(.system(size: blindFontSize, weight: .bold, design: .rounded))
                            .foregroundColor(.white)
                    }
                    .frame(minWidth: 80)
                    
                    Button("+", action: onIncrement)
                        .buttonStyle(.bordered)
                }
                
                VStack(spacing: 4) {
                    Text("Prochain")
                        .font(.caption)
                        .foregroundColor(.white)
                    Text(nextActivityDate ?? "...")
                        .font(.system(size: activityFontSize, weight: .bold, design: .rounded))
                        .foregroundColor(.white)
                }
            }
            .frame(maxWidth: .infinity)
            .padding(.horizontal, 12)
            .padding(.vertical, 10)
            .background(Color.black.opacity(0.7))
            .cornerRadius(8)
        } else {
            HStack(spacing: 30) {
                HStack(spacing: 12) {
                    Button("-", action: onDecrement)
                        .buttonStyle(.bordered)
                        .disabled(playerCount <= 2)
                    
                    VStack(spacing: 4) {
                        Text("Joueurs")
                            .font(.caption)
                            .foregroundColor(.white)
                        Text("\(playerCount)")
                            .font(.system(size: 36, weight: .bold, design: .rounded))
                            .foregroundColor(.white)
                    }
                    .frame(minWidth: 80)
                    
                    Button("+", action: onIncrement)
                        .buttonStyle(.bordered)
                }
                
                VStack(spacing: 4) {
                    Text("Prochain")
                        .font(.caption)
                        .foregroundColor(.white)
                    Text(nextActivityDate ?? "...")
                        .font(.system(size: activityFontSize, weight: .bold, design: .rounded))
                        .foregroundColor(.white)
                }
                .frame(minWidth: 80)
            }
            .frame(maxWidth: .infinity)
            .padding(.horizontal, 12)
            .padding(.vertical, 8)
            .background(Color.black.opacity(0.7))
            .cornerRadius(8)
        }
    }
}

// MARK: - Main View
struct ContentView: View {
    @ObservedObject var viewModel: PokerTimerViewModel
    @State private var showEditor = false
    @State private var showResetConfirmation = false
    @State private var showQuitConfirmation = false
    @State private var alertTimer: Timer?

    var body: some View {
        NavigationStack {
            ZStack {
                Color.black.ignoresSafeArea()

                GeometryReader { geometry in
                    ContentLayout(
                        viewModel: viewModel,
                        size: geometry.size,
                        showEditor: $showEditor,
                        showResetConfirmation: $showResetConfirmation,
                        showQuitConfirmation: $showQuitConfirmation
                    )
                }
            }
            .navigationTitle("Timer")
                                            .foregroundStyle(.yellow)
                                            .minimumScaleFactor(0.8)
                                        Text("Ante: \(viewModel.currentLevel.ante)")
                                            .font(.headline)
                                            .foregroundStyle(.secondary)
                                        Text("Prochain: \(viewModel.nextBlindText)")
                                            .font(.headline)
                                            .foregroundStyle(.blue)
                                            .multilineTextAlignment(.center)
                                    }

                                    VStack(spacing: 12) {
                                        Text("Joueurs")
                                            .font(.caption)
                                            .foregroundColor(.white)
                                        HStack(spacing: 12) {
                                            Button("-") { viewModel.setPlayerCount(viewModel.playerCount - 1) }
                                                .buttonStyle(.bordered)
                                                .disabled(viewModel.playerCount <= 2)
                                            Text("\(viewModel.playerCount)")
                                                .font(.system(size: blindFontSize(for: size), weight: .bold, design: .rounded))
                                                .foregroundColor(.white)
                                                .frame(minWidth: 64)
                                            Button("+") { viewModel.setPlayerCount(viewModel.playerCount + 1) }
                                                .buttonStyle(.bordered)
                                        }

                                        Text("Prochain")
                                            .font(.caption)
                                            .foregroundColor(.white)
                                        Text(viewModel.nextActivityDate ?? "...")
                                            .font(.system(size: activityFontSize(for: size), weight: .bold, design: .rounded))
                                            .foregroundColor(.white)
                                    }
                                    .frame(maxWidth: .infinity)
                                    .padding(.horizontal, 14)
                                    .padding(.vertical, 12)
                                    .background(Color.black.opacity(0.7))
                                    .cornerRadius(10)
                                }
                                .frame(maxWidth: .infinity)

                                VStack(spacing: 12) {
                                    HStack(spacing: 10) {
                                        Button("-1 min") { viewModel.adjustTime(minutes: -1) }
                                            .buttonStyle(.bordered)
                                            .disabled(viewModel.isRunning)
                                        Button("+1 min") { viewModel.adjustTime(minutes: 1) }
                                            .buttonStyle(.bordered)
                                            .disabled(viewModel.isRunning)
                                    }

                                    HStack(spacing: 10) {
                                        Button("◀︎ Niveau") { viewModel.changeLevel(by: -1) }
                                            .buttonStyle(.bordered)
                                            .disabled(viewModel.isRunning || viewModel.currentLevelIndex == 0)
                                        Button("Niveau ▶︎") { viewModel.changeLevel(by: 1) }
                                            .buttonStyle(.bordered)
                                            .disabled(viewModel.isRunning || viewModel.currentLevelIndex >= viewModel.blindLevels.count - 1)
                                    }

                                    HStack(spacing: 12) {
                                        Button(viewModel.isRunning ? "Pause" : "Start") {
                                            viewModel.toggleStartPause()
                                        }
                                        .buttonStyle(.borderedProminent)
                                        .tint(viewModel.isRunning ? .orange : .green)

                                        Button("Restart blind") {
                                            viewModel.restartCurrentBlind()
                                        }
                                        .buttonStyle(.bordered)
                                    }

                                    Divider()

                                    Button("Reset tournoi", role: .destructive) {
                                        showResetConfirmation = true
                                    }
                                    .buttonStyle(.bordered)

                                    Button("Éditer la structure") {
                                        showEditor = true
                                    }
                                    .buttonStyle(.borderedProminent)
                                    .tint(.blue)

                                    Button("Quitter l'app", role: .destructive) {
                                        showQuitConfirmation = true
                                    }
                                    .buttonStyle(.borderedProminent)
                                }
                                .frame(maxWidth: .infinity)
                            }
                        } else {
                            VStack(spacing: isCompactHeight ? 10 : (isCompact ? 14 : 18)) {
                                Text(viewModel.formattedTime)
                                    .font(.system(size: timerFontSize(for: size), weight: .bold, design: .rounded))
                                    .foregroundStyle(.red)
                                    .monospacedDigit()
                                    .minimumScaleFactor(0.8)
                                    .lineLimit(1)

                                VStack(spacing: 8) {
                                    Text("Niveau \(viewModel.currentLevel.level)")
                                        .font(.title2.bold())
                                    Text("\(viewModel.currentLevel.smallBlind)/\(viewModel.currentLevel.bigBlind)")
                                        .font(.system(size: blindFontSize(for: size), weight: .bold, design: .rounded))
                                        .foregroundStyle(.yellow)
                                        .minimumScaleFactor(0.8)
                                    Text("Ante: \(viewModel.currentLevel.ante)")
                                        .font(.headline)
                                        .foregroundStyle(.secondary)
                                    Text("Prochain: \(viewModel.nextBlindText)")
                                        .font(.headline)
                                        .foregroundStyle(.blue)
                                        .multilineTextAlignment(.center)
                                }

                                ViewThatFits(in: .horizontal) {
                                    HStack(spacing: 10) {
                                        Button("-1 min") { viewModel.adjustTime(minutes: -1) }
                                            .buttonStyle(.bordered)
                                            .controlSize(isCompactHeight ? .small : .regular)
                                            .disabled(viewModel.isRunning)
                                        Button("+1 min") { viewModel.adjustTime(minutes: 1) }
                                            .buttonStyle(.bordered)
                                            .controlSize(isCompactHeight ? .small : .regular)
                                            .disabled(viewModel.isRunning)
                                    }
                                    VStack(spacing: 10) {
                                        Button("-1 min") { viewModel.adjustTime(minutes: -1) }
                                            .buttonStyle(.bordered)
                                            .controlSize(isCompactHeight ? .small : .regular)
                                            .disabled(viewModel.isRunning)
                                        Button("+1 min") { viewModel.adjustTime(minutes: 1) }
                                            .buttonStyle(.bordered)
                                            .controlSize(isCompactHeight ? .small : .regular)
                                            .disabled(viewModel.isRunning)
                                    }
                                }

                                ViewThatFits(in: .horizontal) {
                                    HStack(spacing: 10) {
                                        Button("◀︎ Niveau") { viewModel.changeLevel(by: -1) }
                                            .buttonStyle(.bordered)
                                            .controlSize(isCompactHeight ? .small : .regular)
                                            .disabled(viewModel.isRunning || viewModel.currentLevelIndex == 0)

                                        Button("Niveau ▶︎") { viewModel.changeLevel(by: 1) }
                                            .buttonStyle(.bordered)
                                            .controlSize(isCompactHeight ? .small : .regular)
                                            .disabled(viewModel.isRunning || viewModel.currentLevelIndex >= viewModel.blindLevels.count - 1)
                                    }
                                    VStack(spacing: 10) {
                                        Button("◀︎ Niveau") { viewModel.changeLevel(by: -1) }
                                            .buttonStyle(.bordered)
                                            .controlSize(isCompactHeight ? .small : .regular)
                                            .disabled(viewModel.isRunning || viewModel.currentLevelIndex == 0)

                                        Button("Niveau ▶︎") { viewModel.changeLevel(by: 1) }
                                            .buttonStyle(.bordered)
                                            .controlSize(isCompactHeight ? .small : .regular)
                                            .disabled(viewModel.isRunning || viewModel.currentLevelIndex >= viewModel.blindLevels.count - 1)
                                    }
                                }

                                ViewThatFits(in: .horizontal) {
                                    HStack(spacing: 12) {
                                        Button(viewModel.isRunning ? "Pause" : "Start") {
                                            viewModel.toggleStartPause()
                                        }
                                        .buttonStyle(.borderedProminent)
                                        .controlSize(isCompactHeight ? .small : .regular)
                                        .tint(viewModel.isRunning ? .orange : .green)

                                        Button("Restart blind") {
                                            viewModel.restartCurrentBlind()
                                        }
                                        .buttonStyle(.bordered)
                                        .controlSize(isCompactHeight ? .small : .regular)
                                    }
                                    VStack(spacing: 10) {
                                        Button(viewModel.isRunning ? "Pause" : "Start") {
                                            viewModel.toggleStartPause()
                                        }
                                        .buttonStyle(.borderedProminent)
                                        .controlSize(isCompactHeight ? .small : .regular)
                                        .tint(viewModel.isRunning ? .orange : .green)

                                        Button("Restart blind") {
                                            viewModel.restartCurrentBlind()
                                        }
                                        .buttonStyle(.bordered)
                                        .controlSize(isCompactHeight ? .small : .regular)
                                    }
                                }

                                Button("Reset tournoi", role: .destructive) {
                                    showResetConfirmation = true
                                }
                                .buttonStyle(.bordered)

                                Button("Éditer la structure") {
                                    showEditor = true
                                }
                                .buttonStyle(.borderedProminent)
                                .tint(.blue)

                                Button("Quitter l'app", role: .destructive) {
                                    showQuitConfirmation = true
                                }
                                .buttonStyle(.borderedProminent)

                                if isCompact {
                                    VStack(spacing: 14) {
                                        HStack(spacing: 12) {
                                            Button("-") { viewModel.setPlayerCount(viewModel.playerCount - 1) }
                                                .buttonStyle(.bordered)
                                                .disabled(viewModel.playerCount <= 2)

                                            VStack(spacing: 4) {
                                                Text("Joueurs")
                                                    .font(.caption)
                                                    .foregroundColor(.white)
                                                Text("\(viewModel.playerCount)")
                                                    .font(.system(size: blindFontSize(for: size), weight: .bold, design: .rounded))
                                                    .foregroundColor(.white)
                                            }
                                            .frame(minWidth: 80)

                                            Button("+") { viewModel.setPlayerCount(viewModel.playerCount + 1) }
                                                .buttonStyle(.bordered)
                                        }

                                        VStack(spacing: 4) {
                                            Text("Prochain")
                                                .font(.caption)
                                                .foregroundColor(.white)
                                            Text(viewModel.nextActivityDate ?? "...")
                                                .font(.system(size: activityFontSize(for: size), weight: .bold, design: .rounded))
                                                .foregroundColor(.white)
                                        }
                                    }
                                    .frame(maxWidth: .infinity)
                                    .padding(.horizontal, 12)
                                    .padding(.vertical, 10)
                                    .background(Color.black.opacity(0.7))
                                    .cornerRadius(8)
                                } else {
                                    HStack(spacing: 30) {
                                        HStack(spacing: 12) {
                                            Button("-") { viewModel.setPlayerCount(viewModel.playerCount - 1) }
                                                .buttonStyle(.bordered)
                                                .disabled(viewModel.playerCount <= 2)

                                            VStack(spacing: 4) {
                                                Text("Joueurs")
                                                    .font(.caption)
                                                    .foregroundColor(.white)
                                                Text("\(viewModel.playerCount)")
                                                    .font(.system(size: 36, weight: .bold, design: .rounded))
                                                    .foregroundColor(.white)
                                            }
                                            .frame(minWidth: 80)

                                            Button("+") { viewModel.setPlayerCount(viewModel.playerCount + 1) }
                                                .buttonStyle(.bordered)
                                        }

                                        VStack(spacing: 4) {
                                            Text("Prochain")
                                                .font(.caption)
                                                .foregroundColor(.white)
                                            Text(viewModel.nextActivityDate ?? "...")
                                                .font(.system(size: activityFontSize(for: size), weight: .bold, design: .rounded))
                                                .foregroundColor(.white)
                                        }
                                        .frame(minWidth: 80)
                                    }
                                    .frame(maxWidth: .infinity)
                                    .padding(.horizontal, 12)
                                    .padding(.vertical, 8)
                                    .background(Color.black.opacity(0.7))
                                    .cornerRadius(8)
                                }
                            }
                        }
                        .padding(.horizontal, isCompact ? 12 : (isVeryWide ? 28 : 18))
                        .padding(.vertical, isCompactHeight ? 10 : 16)
                        .frame(maxWidth: isVeryWide ? 980 : 760)
                        .frame(maxWidth: .infinity)
                    }
                }
            }
            .navigationTitle("Timer")
            .sheet(isPresented: $showEditor) {
                EditStructureView(levels: viewModel.blindLevels) { updated in
                    viewModel.updateStructure(updated)
                }
            }
            .alert("Confirmation", isPresented: $showResetConfirmation) {
                Button("Annuler", role: .cancel) {}
                Button("Reset", role: .destructive) { viewModel.resetTournament() }
            } message: {
                Text("Réinitialiser le cardevent au niveau 1 ?")
            }
            .alert("Info", isPresented: Binding(
                get: { viewModel.alertMessage != nil },
                set: { if !$0 { 
                    viewModel.alertMessage = nil
                    alertTimer?.invalidate()
                } }
            )) {
                Button("OK", role: .cancel) { 
                    viewModel.alertMessage = nil
                    alertTimer?.invalidate()
                }
            } message: {
                Text(viewModel.alertMessage ?? "")
            }
            .onReceive(viewModel.$alertMessage) { message in
                alertTimer?.invalidate()
                if message != nil {
                    alertTimer = Timer.scheduledTimer(withTimeInterval: 10, repeats: false) { [weak viewModel] _ in
                        Task { @MainActor in
                            viewModel?.alertMessage = nil
                        }
                    }
                }
            }
            .confirmationDialog("Quitter l'application ?", isPresented: $showQuitConfirmation, titleVisibility: .visible) {
                Button("Quitter", role: .destructive) {
                    viewModel.quitApplication()
                }
                Button("Annuler", role: .cancel) { }
            } message: {
                Text("Le cardevent sera stoppé puis l'app sera fermée.")
            }
        }
    }
}
