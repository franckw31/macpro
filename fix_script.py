import re
with open('/Users/franck/Desktop/Viendez.com/xcode/CardEvent/CardEvent/ViewModels/PokerTimerViewModel.swift', 'r') as f:
    text = f.read()

fixed = re.sub(r'let url = URL\(string: registerAPIURL.*?\s+var request = URLRequest\(url: url\)', 'let url = URL(string: registerAPIURL + "?activity_id=\\(actId)&token=\\(token)") else { return }\n        var request = URLRequest(url: url)', text, flags=re.DOTALL)
with open('/Users/franck/Desktop/Viendez.com/xcode/CardEvent/CardEvent/ViewModels/PokerTimerViewModel.swift', 'w') as f:
    f.write(fixed)
