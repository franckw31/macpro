import SwiftUI
import AudioToolbox
import AVFoundation

// Card-suit motif background (mirrors panel/assets/css/card-bg.css)
private struct CardBackground: View {
    private let suits: [String] = ["suit.spade.fill", "suit.heart.fill", "suit.club.fill", "suit.diamond.fill"]

    var body: some View {
        GeometryReader { geo in
            // Tuning: increase symbols slightly from previous iteration for readability
            let stepY: CGFloat = max(32, min(geo.size.width, geo.size.height) / 10)
            ZStack {
                Color.black
                VStack(spacing: stepY * 0.32) {
                    ForEach(0..<Int(ceil(geo.size.height / stepY)) + 1, id: \.self) { row in
                        HStack(spacing: stepY * 0.2) {
                            ForEach(0..<Int(ceil(geo.size.width / (stepY * 0.95))) + 1, id: \.self) { col in
                                Image(systemName: suits[(row + col) % suits.count])
                                    .font(.system(size: stepY * 0.85))
                                    .foregroundColor(((row + col) % 2 == 0) ? Color.white.opacity(0.16) : Color.red.opacity(0.16))
                            }
                        }
                        .frame(maxWidth: .infinity)
                    }
                }
                .frame(width: geo.size.width, height: geo.size.height)
                .contentShape(Rectangle())
                .allowsHitTesting(false)
            }
        }
    }
}

private struct ContentLayout: View {
    @ObservedObject var viewModel: PokerTimerViewModel
    let size: CGSize
    @Binding var showEditor: Bool
    @Binding var showResetConfirmation: Bool
    @Binding var showCountdown: Bool
    
    private var width: CGFloat { size.width }
    private var height: CGFloat { size.height }
    private var isCompact: Bool { width < 430 }
    private var isCompactHeight: Bool { height < 430 }
    private var isVeryWide: Bool { width >= 900 }
    private var isPadLandscape: Bool { width > height && width >= 1000 }
    
    var body: some View {
        if isPadLandscape {
            TwoColumnLayout(
                viewModel: viewModel,
                size: size,
                showEditor: $showEditor,
                showResetConfirmation: $showResetConfirmation,
                showCountdown: $showCountdown
            )
        } else {
            ScrollView {
                SingleColumnLayout(
                    viewModel: viewModel,
                    size: size,
                    isCompact: isCompact,
                    isCompactHeight: isCompactHeight,
                    showEditor: $showEditor,
                    showResetConfirmation: $showResetConfirmation,
                    showCountdown: $showCountdown
                )
                .frame(maxWidth: .infinity)
                .padding(.horizontal, isCompact ? 12 : 20)
                .padding(.vertical, isCompactHeight ? 10 : 16)
            }
        }
    }
}

// MARK: - TwoColumnLayout
private struct TwoColumnLayout: View {
    @ObservedObject var viewModel: PokerTimerViewModel
    let size: CGSize
    @Binding var showEditor: Bool
    @Binding var showResetConfirmation: Bool
    @Binding var showCountdown: Bool
    
    var body: some View {
        HStack(alignment: .center, spacing: 0) {
            leftColumn
            Divider().padding(.vertical, 24)
            rightColumn
        }
        .frame(maxWidth: .infinity, maxHeight: .infinity)
        .padding(.horizontal, 24)
        .padding(.vertical, 16)
    }
    
    private var leftColumn: some View {
        VStack(spacing: 0) {
            Spacer(minLength: 0)
            TimerDisplay(
                time: viewModel.formattedTime,
                progress: viewModel.currentLevel.duration > 0
                    ? Double(viewModel.timeLeft) / Double(viewModel.currentLevel.duration)
                    : 0,
                size: size,
                isRunning: viewModel.isRunning,
                onAdjust: { viewModel.adjustTime(minutes: $0) },
                showCountdown: $showCountdown
            )
            Spacer(minLength: 12)
            BlindInfo(level: viewModel.currentLevel, nextBlindText: viewModel.nextBlindText, size: size, minutesUntilBreak: viewModel.minutesUntilBreakText, totalLevels: viewModel.blindLevels.count)
            Spacer(minLength: 16)
        }
        .frame(maxWidth: .infinity, maxHeight: .infinity)
    }
    
    private var rightColumn: some View {
        VStack(spacing: 0) {
            Spacer(minLength: 0)
            TimeControls(viewModel: viewModel, isSmall: false)
            Spacer(minLength: 12)
            LevelControls(viewModel: viewModel, isSmall: false)
            Spacer(minLength: 12)
            PlaybackControls(viewModel: viewModel, isSmall: false)
            Spacer(minLength: 20)
            Divider()
            Spacer(minLength: 20)
            ActionButtons(
                showEditor: $showEditor,
                showResetConfirmation: $showResetConfirmation,
                showCountdown: $showCountdown,
                wide: true
            )
            Spacer(minLength: 0)
        }
        .frame(maxWidth: .infinity, maxHeight: .infinity)
        .padding(.horizontal, 24)
    }
    
    private var playerActivityInfoPad: some View {
        PlayerActivityInfo(
            playerCount: viewModel.playerCount,
            nextActivityDate: viewModel.nextActivityDate,
            nextActivityBuyin: viewModel.nextActivityBuyin,
            nextActivityRake: viewModel.nextActivityRake,
            size: size,
            isCompact: false,
            canGoBack: viewModel.canGoToPrevActivity,
            canGoForward: viewModel.canGoToNextActivity,
            onPrev: { viewModel.navigateActivity(by: -1) },
            onNext: { viewModel.navigateActivity(by: 1) }
        )
        .frame(maxWidth: .infinity)
    }
}

// MARK: - SingleColumnLayout
private struct SingleColumnLayout: View {
    @ObservedObject var viewModel: PokerTimerViewModel
    @ObservedObject private var auth = AuthService.shared
    
    @Environment(\.colorScheme) private var colorScheme
    let size: CGSize
    let isCompact: Bool
    let isCompactHeight: Bool
    @Binding var showEditor: Bool
    @Binding var showResetConfirmation: Bool
    @Binding var showCountdown: Bool
    
    var body: some View {
        VStack(spacing: isCompactHeight ? 8 : (isCompact ? 12 : 16)) {
            TimerDisplay(
                time: viewModel.formattedTime,
                progress: viewModel.currentLevel.duration > 0
                    ? Double(viewModel.timeLeft) / Double(viewModel.currentLevel.duration)
                    : 0,
                size: size,
                isRunning: viewModel.isRunning,
                onAdjust: { viewModel.adjustTime(minutes: $0) },
                showCountdown: $showCountdown
            )
            BlindInfo(level: viewModel.currentLevel, nextBlindText: viewModel.nextBlindText, size: size, minutesUntilBreak: viewModel.minutesUntilBreakText, totalLevels: viewModel.blindLevels.count)
            
            // Controls grouped in pairs side-by-side
            HStack(spacing: 12) {
                VStack(spacing: 8) {
                    Button("-1 min") { viewModel.adjustTime(minutes: -1) }
                        .buttonStyle(.bordered)
                        .controlSize(isCompactHeight ? .small : .regular)
                        .disabled(viewModel.isRunning)
                        .frame(maxWidth: .infinity)
                    Button("+1 min") { viewModel.adjustTime(minutes: 1) }
                        .buttonStyle(.bordered)
                        .controlSize(isCompactHeight ? .small : .regular)
                        .disabled(viewModel.isRunning)
                        .frame(maxWidth: .infinity)
                }
                VStack(spacing: 8) {
                    Button("◀︎ Niveau") { viewModel.changeLevel(by: -1) }
                        .buttonStyle(.bordered)
                        .controlSize(isCompactHeight ? .small : .regular)
                        .disabled(viewModel.isRunning || viewModel.currentLevelIndex == 0)
                        .frame(maxWidth: .infinity)
                    Button("Niveau ▶︎") { viewModel.changeLevel(by: 1) }
                        .buttonStyle(.bordered)
                        .controlSize(isCompactHeight ? .small : .regular)
                        .disabled(viewModel.isRunning || viewModel.currentLevelIndex >= viewModel.blindLevels.count - 1)
                        .frame(maxWidth: .infinity)
                }
            }
            
            // Start/Pause + Restart
            HStack(spacing: 12) {
                Button(viewModel.isRunning ? "Pause" : "Start") {
                    viewModel.toggleStartPause()
                }
                .buttonStyle(.borderedProminent)
                .controlSize(isCompactHeight ? .small : .large)
                .tint((viewModel.isRunning ? Color.orange : Color.green).opacity(0.75))
                .frame(maxWidth: .infinity)
                
                Button("Re-Start", role: .destructive) { showResetConfirmation = true }
                    .buttonStyle(.borderedProminent)
                    .tint(.red)
                    .foregroundStyle(.white)
                    .controlSize(isCompactHeight ? .small : .large)
                    .frame(maxWidth: .infinity)
            }
            
            // Action buttons in a row
            VStack(spacing: 6) {
                HStack(spacing: 12) {
                    Button("Restart blind") {
                        viewModel.restartCurrentBlind()
                    }
                    .buttonStyle(.borderedProminent)
                    .tint(Color.yellow.opacity(0.9))
                    .foregroundStyle(.black)
                    .frame(maxWidth: .infinity)
                    Button("Structures") { showEditor = true }
                        .buttonStyle(.borderedProminent)
                        .tint(.blue)
                        .frame(maxWidth: .infinity)
                    Button(action: { showCountdown.toggle() }) {
                        Label(showCountdown ? "Annuler" : "30 sec", systemImage: "timer")
                    }
                    .buttonStyle(.borderedProminent)
                    .tint(showCountdown ? .red : .indigo)
                    .frame(maxWidth: .infinity)
                }
                .controlSize(isCompactHeight ? .small : .regular)

                // Connected-user action buttons removed
            }
        }
    }
}

// MARK: - Component Views
private struct TimerDisplay: View {
    let time: String
    let progress: Double
    let size: CGSize
    let isRunning: Bool
    let onAdjust: (Int) -> Void  // minutes à ajouter (négatif = retirer)
    @Binding var showCountdown: Bool
    @StateObject private var beepEngine = BeepEngine()
    @State private var countdownLeft: Int = 30
    @State private var countdownTimer: Timer?
    @State private var dragAccum: CGFloat = 0
    @State private var dragDelta: Int = 0        // minutes affichées pendant le drag
    @State private var isDragging: Bool = false

    private var circleSize: CGFloat {
        let w = size.width
        if w < 360 { return 200 }
        if w < 430 { return 240 }
        if w < 600 { return 280 }
        return 320
    }

    private var fontSize: CGFloat {
        let w = size.width
        if w < 360 { return 56 }
        if w < 430 { return 68 }
        if w < 600 { return 80 }
        if w < 800 { return 96 }
        return 112
    }

    private var strokeWidth: CGFloat { circleSize * 0.045 }
    private var cyanColor: Color { Color(red: 0, green: 0.82, blue: 1) }
    // 60 pts de glissement = 1 minute
    private let pixelsPerMinute: CGFloat = 60

    var body: some View {
        ZStack {
            // Cercle de fond
            Circle()
                .stroke(Color.white.opacity(0.1), lineWidth: strokeWidth)
                .frame(width: circleSize, height: circleSize)

            // Cercle de progression néon cyan
            Circle()
                .trim(from: 0, to: showCountdown ? CGFloat(countdownLeft) / 30.0 : CGFloat(max(0, min(1, progress))))
                .stroke(
                    showCountdown
                        ? (countdownLeft <= 5 ? Color.orange : cyanColor)
                        : cyanColor,
                    style: StrokeStyle(lineWidth: strokeWidth, lineCap: .round)
                )
                .frame(width: circleSize, height: circleSize)
                .rotationEffect(.degrees(-90))
                .shadow(color: (showCountdown && countdownLeft <= 5 ? Color.orange : cyanColor).opacity(0.8), radius: 10)
                .animation(.linear(duration: 1), value: progress)
                .animation(.linear(duration: 1), value: countdownLeft)

            // Texte central
            Group {
                if isDragging {
                    VStack(spacing: 4) {
                        Text(dragDelta >= 0 ? "+\(dragDelta) min" : "\(dragDelta) min")
                            .font(.system(size: fontSize * 0.45, weight: .bold, design: .rounded))
                            .foregroundColor(dragDelta >= 0 ? cyanColor : .orange)
                        Text("↑ + / ↓ −")
                            .font(.caption)
                            .foregroundColor(.white.opacity(0.6))
                    }
                } else if showCountdown {
                    Text(countdownLeft > 0 ? "\(countdownLeft)" : "⏱")
                        .font(.system(size: fontSize, weight: .bold, design: .rounded))
                        .foregroundColor(countdownLeft <= 5 ? .orange : .white)
                        .contentTransition(.numericText())
                        .animation(.default, value: countdownLeft)
                } else {
                    Text(time)
                        .font(.system(size: fontSize, weight: .bold, design: .rounded))
                        .foregroundStyle(.red)
                        .monospacedDigit()
                        .minimumScaleFactor(0.6)
                        .lineLimit(1)
                }
            }
            .frame(width: circleSize * 0.8)
        }
        .frame(width: circleSize, height: circleSize)
        .frame(maxWidth: .infinity)
        .gesture(
            DragGesture(minimumDistance: 10)
                .onChanged { value in
                    guard !showCountdown else { return }
                    isDragging = true
                    dragAccum = -value.translation.height  // haut = positif
                    dragDelta = Int((dragAccum / pixelsPerMinute).rounded())
                }
                .onEnded { _ in
                    guard !showCountdown else { return }
                    isDragging = false
                    if dragDelta != 0 {
                        onAdjust(dragDelta)
                    }
                    dragAccum = 0
                    dragDelta = 0
                }
        )
        .onChange(of: showCountdown) { active in
            if active { startCountdown() } else { stopCountdown() }
        }
    }

    private func startCountdown() {
        countdownLeft = 30
        countdownTimer?.invalidate()
        countdownTimer = Timer.scheduledTimer(withTimeInterval: 1, repeats: true) { _ in
            if countdownLeft > 0 {
                countdownLeft -= 1
                if countdownLeft <= 5 { beepEngine.beep() }
            } else {
                stopCountdown()
            }
        }
    }

    private func stopCountdown() {
        countdownTimer?.invalidate()
        countdownTimer = nil
        showCountdown = false
    }
}

private struct BlindInfo: View {
    let level: BlindLevel
    let nextBlindText: String
    let size: CGSize
    var minutesUntilBreak: String? = nil
    var totalLevels: Int = 0
    
    private var fontSize: CGFloat {
        let w = size.width
        if w < 360 { return 38 }
        if w < 430 { return 46 }
        if w < 600 { return 56 }
        if w < 800 { return 66 }
        return 76
    }
    
    var body: some View {
        VStack(spacing: 6) {
            Text("Niveau \(level.level) / \(totalLevels)")
                 .font(.title2.bold())
                 .foregroundColor(.white)
            Text("\(level.smallBlind) / \(level.bigBlind)")
                .font(.system(size: fontSize, weight: .bold, design: .rounded))
                .foregroundStyle(Color.yellow)
                .shadow(color: .black, radius: 0, x:  1, y:  1)
                .shadow(color: .black, radius: 0, x: -1, y: -1)
                .shadow(color: .black, radius: 0, x:  1, y: -1)
                .shadow(color: .black, radius: 0, x: -1, y:  1)
                .minimumScaleFactor(0.7)
                .lineLimit(1)
                .frame(maxWidth: .infinity)
            if level.ante > 0 {
                Text("Ante: \(level.ante)")
                    .font(.headline)
                    .foregroundStyle(.secondary)
            }
            Text("Prochain: \(nextBlindText)")
                .font(.headline)
                .foregroundStyle(.blue)
                .multilineTextAlignment(.center)
            if let pauseText = minutesUntilBreak {
                Text(pauseText)
                    .font(.subheadline)
                    .foregroundStyle(Color.yellow)
                    .shadow(color: .black, radius: 0, x:  1, y:  1)
                    .shadow(color: .black, radius: 0, x: -1, y: -1)
                    .shadow(color: .black, radius: 0, x:  1, y: -1)
                    .shadow(color: .black, radius: 0, x: -1, y:  1)
                    .multilineTextAlignment(.center)
            }
        }
    }
}

private struct TimeControls: View {
    @ObservedObject var viewModel: PokerTimerViewModel
    let isSmall: Bool
    
    var body: some View {
        ViewThatFits(in: .horizontal) {
            HStack(spacing: 10) {
                buttons
            }
            VStack(spacing: 10) {
                buttons
            }
        }
    }
    
    @ViewBuilder
    private var buttons: some View {
        Button("-1 min") { viewModel.adjustTime(minutes: -1) }
            .buttonStyle(.bordered)
            .controlSize(isSmall ? .small : .regular)
            .disabled(viewModel.isRunning)
        Button("+1 min") { viewModel.adjustTime(minutes: 1) }
            .buttonStyle(.bordered)
            .controlSize(isSmall ? .small : .regular)
            .disabled(viewModel.isRunning)
    }
}

private struct LevelControls: View {
    @ObservedObject var viewModel: PokerTimerViewModel
    let isSmall: Bool
    
    var body: some View {
        ViewThatFits(in: .horizontal) {
            HStack(spacing: 10) {
                buttons
            }
            VStack(spacing: 10) {
                buttons
            }
        }
    }
    
    @ViewBuilder
    private var buttons: some View {
        Button("◀︎ Niveau") { viewModel.changeLevel(by: -1) }
            .buttonStyle(.bordered)
            .controlSize(isSmall ? .small : .regular)
            .disabled(viewModel.isRunning || viewModel.currentLevelIndex == 0)
        Button("Niveau ▶︎") { viewModel.changeLevel(by: 1) }
            .buttonStyle(.bordered)
            .controlSize(isSmall ? .small : .regular)
            .disabled(viewModel.isRunning || viewModel.currentLevelIndex >= viewModel.blindLevels.count - 1)
    }
}

private struct PlaybackControls: View {
    @ObservedObject var viewModel: PokerTimerViewModel
    let isSmall: Bool
    
    var body: some View {
        ViewThatFits(in: .horizontal) {
            HStack(spacing: 12) {
                buttons
            }
            VStack(spacing: 10) {
                buttons
            }
        }
    }
    
    @ViewBuilder
    private var buttons: some View {
        Button(viewModel.isRunning ? "Pause" : "Start") {
            viewModel.toggleStartPause()
        }
        .buttonStyle(.borderedProminent)
        .controlSize(isSmall ? .small : .regular)
        .tint(viewModel.isRunning ? .orange : .green)
        
        Button("Restart blind") {
            viewModel.restartCurrentBlind()
        }
        .buttonStyle(.bordered)
        .controlSize(isSmall ? .small : .regular)
    }
}

private struct ActionButtons: View {
    @Binding var showEditor: Bool
    @Binding var showResetConfirmation: Bool
    @Binding var showCountdown: Bool
    var wide: Bool = false
    
    var body: some View {
        VStack(spacing: 10) {
            Button("Reset tournoi", role: .destructive) {
                showResetConfirmation = true
            }
            .buttonStyle(.bordered)
            .frame(maxWidth: wide ? .infinity : nil)
            
            Button("Éditer la structure") {
                showEditor = true
            }
            .buttonStyle(.borderedProminent)
            .tint(.blue)
            .frame(maxWidth: wide ? .infinity : nil)
            
            Button(action: { showCountdown = true }) {
                Label("30 secondes", systemImage: "timer")
            }
            .buttonStyle(.borderedProminent)
            .tint(.indigo)
            .frame(maxWidth: wide ? .infinity : nil)
        }
    }
}

private struct PlayerActivityInfo: View {
    let playerCount: Int
    let nextActivityDate: String?
    let nextActivityBuyin: Int?
    let nextActivityRake: Int?
    let size: CGSize
    let isCompact: Bool
    let canGoBack: Bool
    let canGoForward: Bool
    let onPrev: () -> Void
    let onNext: () -> Void
    
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
        HStack(spacing: 0) {
            // Bouton précédent
            Button(action: onPrev) {
                Image(systemName: "chevron.left")
                    .font(.system(size: 14, weight: .bold))
                    .foregroundColor(canGoBack ? .white : .white.opacity(0.2))
                    .frame(width: 32, height: 44)
                    .contentShape(Rectangle())
            }
            .disabled(!canGoBack)

            // Contenu central
            HStack(spacing: 0) {
                infoCell(label: "Inscrits", value: "\(playerCount)")
                Divider().frame(height: 36).background(Color.white.opacity(0.3))
                infoCell(label: "Prochain", value: nextActivityDate ?? "...")
                if let buyin = nextActivityBuyin {
                    Divider().frame(height: 36).background(Color.white.opacity(0.3))
                    infoCell(label: "Buy-in", value: "\(buyin)€")
                }
                if let rake = nextActivityRake {
                    Divider().frame(height: 36).background(Color.white.opacity(0.3))
                    infoCell(label: "Rake", value: "\(rake)€")
                }
            }
            .frame(maxWidth: .infinity)

            // Bouton suivant
            Button(action: onNext) {
                Image(systemName: "chevron.right")
                    .font(.system(size: 14, weight: .bold))
                    .foregroundColor(canGoForward ? .white : .white.opacity(0.2))
                    .frame(width: 32, height: 44)
                    .contentShape(Rectangle())
            }
            .disabled(!canGoForward)
        }
        .frame(maxWidth: .infinity)
        .padding(.horizontal, 4)
        .padding(.vertical, 8)
        .background(Color.black.opacity(0.7))
        .cornerRadius(8)
    }

    private func infoCell(label: String, value: String) -> some View {
        VStack(spacing: 2) {
            Text(label)
                .font(.caption2)
                .foregroundColor(.white.opacity(0.7))
            Text(value)
                .font(.system(size: 20, weight: .bold, design: .rounded))
                .foregroundColor(.white)
                .minimumScaleFactor(0.6)
                .lineLimit(1)
        }
        .frame(maxWidth: .infinity)
    }
}


// MARK: - Main ContentView
struct ContentView: View {
    @ObservedObject var viewModel: PokerTimerViewModel
    @State private var showEditor = false
    @State private var showResetConfirmation = false
    @State private var showCountdown = false
    @State private var showLive = false

    var body: some View {
        NavigationStack {
            VStack(spacing: 0) {
                GeometryReader { geometry in
                    ContentLayout(
                        viewModel: viewModel,
                        size: geometry.size,
                        showEditor: $showEditor,
                        showResetConfirmation: $showResetConfirmation,
                        showCountdown: $showCountdown
                    )
                }
                // PlayerActivityInfo removed per request
            }
            .background {
                CardBackground()
                    .ignoresSafeArea()
            }
            .navigationBarTitleDisplayMode(.inline)
            .toolbar {
                ToolbarItem(placement: .principal) {
                    Text(viewModel.currentStructureName.isEmpty ? "Timer" : "Timer : \(viewModel.currentStructureName)")
                        .font(.title3.weight(.bold))
                        .foregroundColor(.white)
                }
            }
            .toolbarColorScheme(.dark, for: .navigationBar)
            .sheet(isPresented: $showEditor) {
                StructureLibraryView(currentLevels: viewModel.blindLevels) { updated, name in
                    viewModel.updateStructure(updated, name: name)
                }
            }
            .alert("Confirmation", isPresented: $showResetConfirmation) {
                Button("Annuler", role: .cancel) {}
                Button("Reset", role: .destructive) { viewModel.resetTournament() }
            } message: {
                Text("Réinitialiser le cardevent au niveau 1 ?")
            }
        }
    }
}

// MARK: - Beep Engine
final class BeepEngine: ObservableObject {
    private let engine = AVAudioEngine()
    private let player = AVAudioPlayerNode()
    private var buffer: AVAudioPCMBuffer?

    init() {
        let sampleRate: Double = 44100
        let duration: Double = 0.12
        let frequency: Double = 880
        let frameCount = AVAudioFrameCount(sampleRate * duration)
        guard let format = AVAudioFormat(standardFormatWithSampleRate: sampleRate, channels: 1),
              let buf = AVAudioPCMBuffer(pcmFormat: format, frameCapacity: frameCount) else { return }
        buf.frameLength = frameCount
        let data = buf.floatChannelData![0]
        for i in 0..<Int(frameCount) {
            let t = Double(i) / sampleRate
            let fade = min(t / 0.008, min(1.0, (duration - t) / 0.015))
            data[i] = Float(sin(2 * .pi * frequency * t) * 0.95 * fade)
        }
        buffer = buf
        engine.attach(player)
        engine.connect(player, to: engine.mainMixerNode, format: format)
        try? AVAudioSession.sharedInstance().setCategory(.playback, mode: .default, options: .mixWithOthers)
        try? AVAudioSession.sharedInstance().setActive(true)
        try? engine.start()
    }

    func beep() {
        guard let buffer else { return }
        if !player.isPlaying { player.play() }
        player.scheduleBuffer(buffer)
    }
}

