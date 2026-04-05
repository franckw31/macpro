import SwiftUI

// MARK: - API Model

private struct TimerAPIResponse: Decodable {
    let status: String
    let seconds_remaining: Int?
    let duration_seconds: Int?
    let blinds_text: String?
    let ante_text: String?
    let level_name: String?
    let next_blinds_text: String?
    let next_pause: String?
    let is_paused: Bool?
    let players_active: Int?
    let players_total: Int?
    let avg_stack: String?
}

// MARK: - LiveTimerViewModel

@MainActor
private final class LiveTimerViewModel: ObservableObject {
    @Published var secondsRemaining: Int = 0
    @Published var durationSeconds: Int = 1
    @Published var blindsText: String = "--/--"
    @Published var anteText: String = ""
    @Published var levelName: String = "Niveau --"
    @Published var nextBlindsText: String = ""
    @Published var nextPause: String = ""
    @Published var isPaused: Bool = false
    @Published var playersActive: Int = 0
    @Published var playersTotal: Int = 0
    @Published var avgStack: String = ""
    @Published var status: String = "loading"

    private var tickTimer: Timer?
    private var syncTimer: Timer?
    private let activityId: Int

    init(activityId: Int) {
        self.activityId = activityId
    }

    func start() {
        Task { await sync() }
        syncTimer = Timer.scheduledTimer(withTimeInterval: 5, repeats: true) { [weak self] _ in
            Task { await self?.sync() }
        }
        tickTimer = Timer.scheduledTimer(withTimeInterval: 1, repeats: true) { [weak self] _ in
            guard let self else { return }
            Task { @MainActor in
                if !self.isPaused && self.secondsRemaining > 0 {
                    self.secondsRemaining -= 1
                }
            }
        }
    }

    func stop() {
        tickTimer?.invalidate()
        syncTimer?.invalidate()
        tickTimer = nil
        syncTimer = nil
    }

    private func sync() async {
        guard let url = URL(string: "https://viendez.com/panel/cardevent-api.php?uid=\(activityId)") else { return }
        do {
            let (data, _) = try await URLSession.shared.data(from: url)
            let resp = try JSONDecoder().decode(TimerAPIResponse.self, from: data)
            status = resp.status
            isPaused = resp.is_paused ?? false
            secondsRemaining = max(0, resp.seconds_remaining ?? 0)
            durationSeconds = max(1, resp.duration_seconds ?? 1)
            blindsText = resp.blinds_text ?? "--/--"
            anteText = resp.ante_text ?? ""
            levelName = resp.level_name ?? "Niveau --"
            nextBlindsText = resp.next_blinds_text ?? ""
            nextPause = resp.next_pause ?? ""
            playersActive = resp.players_active ?? 0
            playersTotal = resp.players_total ?? 0
            avgStack = resp.avg_stack ?? ""
        } catch {
            // Garde le dernier état connu en cas d'erreur réseau
        }
    }

    var formattedTime: String {
        let s = max(0, secondsRemaining)
        return String(format: "%02d:%02d", s / 60, s % 60)
    }

    var progress: Double {
        guard durationSeconds > 0 else { return 0 }
        return min(1, max(0, Double(secondsRemaining) / Double(durationSeconds)))
    }
}

// MARK: - LiveView

struct LiveView: View {
    let activityId: Int
    let activityTitle: String
    @StateObject private var liveVM: LiveTimerViewModel
    @Environment(\.dismiss) private var dismiss
    @State private var showCountdown: Bool = false
    @State private var countdownLeft: Int = 30
    @State private var countdownTimer: Timer? = nil
    @StateObject private var beepEngine = BeepEngine()
    @State private var showPlayers: Bool = false

    private var cyanColor: Color { Color(red: 0, green: 0.82, blue: 1) }

    init(activityId: Int, activityTitle: String = "") {
        self.activityId = activityId
        self.activityTitle = activityTitle
        _liveVM = StateObject(wrappedValue: LiveTimerViewModel(activityId: activityId))
    }

    var body: some View {
        GeometryReader { geo in
            let w = geo.size.width
            let h = geo.size.height
            let isLandscape = w > h
            let minDim = min(w, h)
            let circleSize: CGFloat = isLandscape ? minDim * 0.65 : minDim * 0.72
            let timerFont: CGFloat = circleSize * 0.28
            let blindFont: CGFloat = minDim * (isLandscape ? 0.10 : 0.11)
            let labelFont: CGFloat = minDim * 0.04

            ZStack {
                Color.black.ignoresSafeArea()

                if liveVM.status == "loading" {
                    ProgressView().tint(cyanColor).scaleEffect(2)
                } else if liveVM.status == "finished" {
                    finishedView(labelFont: labelFont)
                } else if isLandscape {
                    landscapeLayout(w: w, h: h,
                                    circleSize: circleSize,
                                    timerFont: timerFont,
                                    blindFont: blindFont,
                                    labelFont: labelFont)
                } else {
                    portraitLayout(h: h,
                                   circleSize: circleSize,
                                   timerFont: timerFont,
                                   blindFont: blindFont,
                                   labelFont: labelFont)
                }
            }
        }
        .navigationBarTitleDisplayMode(.inline)
        .toolbar {
            ToolbarItem(placement: .navigationBarLeading) {
                Button { dismiss() } label: {
                    HStack(spacing: 4) {
                        Image(systemName: "chevron.left")
                            .font(.system(size: 14, weight: .semibold))
                        Text("Retour")
                            .font(.subheadline)
                    }
                    .foregroundColor(cyanColor)
                }
            }
            ToolbarItem(placement: .principal) {
                VStack(spacing: 1) {
                    Text("Live")
                        .font(.headline.bold())
                        .foregroundColor(cyanColor)
                    if !activityTitle.isEmpty {
                        Text(activityTitle)
                            .font(.caption2)
                            .foregroundColor(.white.opacity(0.5))
                            .lineLimit(1)
                    }
                }
            }
            ToolbarItem(placement: .navigationBarTrailing) {
                HStack(spacing: 20) {
                    Button {
                        showCountdown.toggle()
                    } label: {
                        ZStack(alignment: .bottomTrailing) {
                            Image(systemName: showCountdown ? "cardevent.circle.fill" : "cardevent.circle")
                                .font(.title3)
                                .foregroundColor(showCountdown ? .red : .indigo)
                                .symbolRenderingMode(.hierarchical)
                            if showCountdown {
                                Text("\(countdownLeft)")
                                    .font(.system(size: 9, weight: .bold))
                                    .foregroundColor(.white)
                                    .padding(2)
                                    .background(Color.red)
                                    .clipShape(Circle())
                                    .offset(x: 4, y: 4)
                            }
                        }
                    }
                    .contentShape(Rectangle())

                    Button { dismiss() } label: {
                        Image(systemName: "xmark.circle.fill")
                            .font(.title3)
                            .foregroundColor(.white.opacity(0.6))
                    }
                    .contentShape(Rectangle())
                }
            }
        }
        .preferredColorScheme(.dark)
        .onAppear { liveVM.start() }
        .onDisappear {
            liveVM.stop()
            stopCountdown()
        }
        .onChange(of: showCountdown) { active in
            if active { startCountdown() } else { stopCountdown() }
        }
    }

    // MARK: - Layouts

    private func portraitLayout(h: CGFloat,
                                circleSize: CGFloat,
                                timerFont: CGFloat,
                                blindFont: CGFloat,
                                labelFont: CGFloat) -> some View {
        VStack(spacing: h * 0.03) {
            Spacer(minLength: 0)
            circleTimer(size: circleSize, timerFont: timerFont, labelFont: labelFont)
            blindsBlock(blindFont: blindFont, labelFont: labelFont)
            nextInfoBlock(labelFont: labelFont)
            statsBlock(labelFont: labelFont)
            playersButton
            Spacer(minLength: 0)
        }
        .padding(.horizontal, 20)
    }

    private func landscapeLayout(w: CGFloat, h: CGFloat,
                                 circleSize: CGFloat,
                                 timerFont: CGFloat,
                                 blindFont: CGFloat,
                                 labelFont: CGFloat) -> some View {
        HStack(spacing: 0) {
            circleTimer(size: circleSize, timerFont: timerFont, labelFont: labelFont)
                .frame(maxWidth: w * 0.5)
            VStack(spacing: h * 0.06) {
                Spacer()
                blindsBlock(blindFont: blindFont, labelFont: labelFont)
                nextInfoBlock(labelFont: labelFont)
                statsBlock(labelFont: labelFont)
                playersButton
                Spacer()
            }
            .frame(maxWidth: w * 0.5)
            .padding(.horizontal, 16)
        }
    }

    // MARK: - Subviews

    private func circleTimer(size: CGFloat, timerFont: CGFloat, labelFont: CGFloat) -> some View {
        let strokeW = size * 0.045
        let cdColor: Color = countdownLeft <= 5 ? .orange : cyanColor
        return ZStack {
            Circle()
                .stroke(Color.white.opacity(0.08), lineWidth: strokeW)
                .frame(width: size, height: size)

            Circle()
                .trim(from: 0, to: showCountdown
                    ? CGFloat(countdownLeft) / 30.0
                    : CGFloat(liveVM.progress))
                .stroke(
                    showCountdown ? cdColor : (liveVM.isPaused ? Color.orange : cyanColor),
                    style: StrokeStyle(lineWidth: strokeW, lineCap: .round)
                )
                .frame(width: size, height: size)
                .rotationEffect(.degrees(-90))
                .shadow(color: (showCountdown ? cdColor : (liveVM.isPaused ? Color.orange : cyanColor)).opacity(0.7), radius: 12)
                .animation(.linear(duration: 1), value: liveVM.secondsRemaining)
                .animation(.linear(duration: 1), value: countdownLeft)

            VStack(spacing: 4) {
                if showCountdown {
                    Text(countdownLeft > 0 ? "\(countdownLeft)" : "⏱")
                        .font(.system(size: timerFont, weight: .bold, design: .rounded))
                        .foregroundColor(cdColor)
                        .shadow(color: cdColor.opacity(0.6), radius: 16)
                        .contentTransition(.numericText())
                        .animation(.default, value: countdownLeft)
                } else {
                    Text(liveVM.levelName)
                        .font(.system(size: labelFont, weight: .light, design: .rounded))
                        .foregroundColor(.white.opacity(0.5))
                        .textCase(.uppercase)
                        .tracking(2)
                        .lineLimit(1)
                        .minimumScaleFactor(0.5)

                    Text(liveVM.isPaused ? "PAUSE" : liveVM.formattedTime)
                        .font(.system(size: timerFont, weight: .bold, design: .rounded))
                        .foregroundColor(liveVM.isPaused ? .orange : cyanColor)
                        .monospacedDigit()
                        .shadow(color: (liveVM.isPaused ? Color.orange : cyanColor).opacity(0.6), radius: 16)
                        .minimumScaleFactor(0.5)
                        .lineLimit(1)
                        .contentTransition(.numericText())
                        .animation(.default, value: liveVM.secondsRemaining)

                    if !liveVM.anteText.isEmpty {
                        Text(liveVM.anteText)
                            .font(.system(size: labelFont * 0.9, weight: .medium))
                            .foregroundColor(cyanColor.opacity(0.8))
                    }
                }
            }
            .frame(width: size * 0.75)
        }
        .frame(width: size, height: size)
    }

    // MARK: - Countdown

    private func startCountdown() {
        countdownLeft = 30
        countdownTimer?.invalidate()
        countdownTimer = Timer.scheduledTimer(withTimeInterval: 1, repeats: true) { _ in
            Task { @MainActor in
                if countdownLeft > 0 {
                    countdownLeft -= 1
                    if countdownLeft <= 5 { beepEngine.beep() }
                } else {
                    stopCountdown()
                }
            }
        }
    }

    private func stopCountdown() {
        countdownTimer?.invalidate()
        countdownTimer = nil
        showCountdown = false
    }

    private func blindsBlock(blindFont: CGFloat, labelFont: CGFloat) -> some View {
        VStack(spacing: 4) {
            Text(liveVM.blindsText)
                .font(.system(size: blindFont, weight: .bold, design: .rounded))
                .foregroundColor(.yellow)
                .shadow(color: .black, radius: 2, x: 1, y: 1)
                .minimumScaleFactor(0.5)
                .lineLimit(1)
            Text("Blindes")
                .font(.system(size: labelFont, weight: .regular))
                .foregroundColor(.white.opacity(0.4))
                .textCase(.uppercase)
                .tracking(3)
        }
    }

    private func nextInfoBlock(labelFont: CGFloat) -> some View {
        VStack(spacing: 6) {
            if !liveVM.nextBlindsText.isEmpty {
                HStack(spacing: 6) {
                    Text("→").foregroundColor(.white.opacity(0.3))
                    Text(liveVM.nextBlindsText)
                        .font(.system(size: labelFont * 1.1, weight: .semibold))
                        .foregroundColor(.white.opacity(0.6))
                }
            }
            if !liveVM.nextPause.isEmpty {
                Text(liveVM.nextPause)
                    .font(.system(size: labelFont * 0.9, weight: .regular))
                    .foregroundColor(.orange.opacity(0.7))
            }
        }
    }

    private var playersButton: some View {
        NavigationLink(destination: PlayersLiveView(activityId: activityId, activityTitle: activityTitle)) {
            Label("Activité des joueurs", systemImage: "person.3.fill")
                .font(.system(size: 14, weight: .semibold))
                .foregroundColor(.white)
                .padding(.horizontal, 18)
                .padding(.vertical, 10)
                .frame(maxWidth: .infinity)
                .background(Color.white.opacity(0.1))
                .cornerRadius(12)
                .overlay(
                    RoundedRectangle(cornerRadius: 12)
                        .stroke(Color.white.opacity(0.15), lineWidth: 1)
                )
        }
    }

    private func statsBlock(labelFont: CGFloat) -> some View {
        HStack(spacing: 20) {
            if liveVM.playersTotal > 0 {
                statCell(label: "Joueurs",
                         value: "\(liveVM.playersActive)/\(liveVM.playersTotal)",
                         size: labelFont)
            }
            if !liveVM.avgStack.isEmpty {
                statCell(label: "Stack Moy.", value: liveVM.avgStack, size: labelFont)
            }
        }
        .padding(.horizontal, 16)
        .padding(.vertical, 8)
        .background(Color.white.opacity(0.05))
        .cornerRadius(10)
    }

    private func statCell(label: String, value: String, size: CGFloat) -> some View {
        VStack(spacing: 2) {
            Text(value)
                .font(.system(size: size * 1.1, weight: .bold, design: .rounded))
                .foregroundColor(.white)
            Text(label)
                .font(.system(size: size * 0.75, weight: .regular))
                .foregroundColor(.white.opacity(0.4))
                .textCase(.uppercase)
                .tracking(2)
        }
    }

    private func finishedView(labelFont: CGFloat) -> some View {
        VStack(spacing: 16) {
            Image(systemName: "checkmark.circle")
                .font(.system(size: 60))
                .foregroundColor(.green.opacity(0.7))
            Text("Tournoi terminé")
                .font(.system(size: labelFont * 1.5, weight: .bold))
                .foregroundColor(.white.opacity(0.7))
        }
    }
}
