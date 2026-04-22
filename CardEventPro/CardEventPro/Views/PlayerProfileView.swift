import SwiftUI
#if canImport(UIKit)
import UIKit
#endif

// ImagePicker is provided in Views/ImagePicker.swift and registered in the project

// MARK: - PlayerStats Model

private struct PlayerStats {
    let photoUrl: String
    let nbParties: Int
    let nbPartiesWithGain: Int
    let totalGains: Double
    let totalBuyins: Double

