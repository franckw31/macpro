import Foundation

let jsonString = """
{"success":true,"member_id":2,"count":30,"tickets":[{"id_indiv":2,"collection_id":498,"name":"DQY-PG7N-TVK-BZD","value":1,"date":"2026-03-30 19:45:00","aff_rake":0}],"monthly_totals":{"2026-01":135,"2026-02":129,"2026-03":185}}
"""
let data = jsonString.data(using: .utf8)!

do {
    let json = try JSONSerialization.jsonObject(with: data) as? [String: Any]
    if let success = json?["success"] as? Bool, success {
        if let arr = json?["tickets"] as? [[String: Any]] {
            print("Found \(arr.count) tickets")
        } else {
            print("No tickets array")
        }
    } else {
        print("Success false")
    }
} catch {
    print("Caught: \(error)")
}
