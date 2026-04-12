import shutil, os

SRC = '/Users/franck/Desktop/Viendez.com/xcode/CardEvent/CardEvent'
DST = '/Users/franck/Desktop/Viendez.com/xcode/CardEventPro/CardEventPro'

files = {
    'Models': ['BlindLevel.swift', 'BlindStructure.swift'],
    'ViewModels': ['PokerTimerViewModel.swift'],
    'Services': ['AuthService.swift'],
    'Views': ['ContentView.swift', 'EditStructureView.swift', 'StructureLibraryView.swift',
              'LoginView.swift', 'ParticipantsListView.swift', 'PlayerProfileView.swift',
              'ActivityInfoView.swift', 'PlayersMovementsView.swift', 'LiveView.swift',
              'PlayersLiveView.swift', 'ChallengeRankingView.swift', 'PlayerStatsDetailView.swift',
              'PlayerTrakView.swift', 'TicketsListView.swift', 'HomeView.swift']
}

for folder, flist in files.items():
    for f in flist:
        src = os.path.join(SRC, folder, f)
        dst = os.path.join(DST, folder, f)
        if os.path.exists(src):
            shutil.copy2(src, dst)
            print(f'Copied {folder}/{f}')
        else:
            print(f'MISSING: {src}')

print("Done!")
