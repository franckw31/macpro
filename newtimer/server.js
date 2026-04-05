const WebSocket = require('ws');
const port = 8181;

const wss = new WebSocket.Server({ port });
console.log(`WebSocket server started on port ${port}`);

let timerState = {
    currentLevel: 0,
    timeLeft: 1200,
    isRunning: false,
    lastUpdate: Date.now(),
    blindLevels: [] // Store blind levels
};

let ws;
let isLocalUpdate = false;

function initWebSocket() {
    ws = new WebSocket(WS_HOST);
    
    ws.onopen = function() {
        console.log('Connected to WebSocket server');
    };
    
    ws.onmessage = function(event) {
        try {
            const message = JSON.parse(event.data);
            if (message.type === 'sync') {
                syncTimerState(message.data);
            }
        } catch (e) {
            console.error('Error processing message:', e);
        }
    };
    
    ws.onclose = function() {
        console.log('Disconnected from WebSocket server');
        // Try to reconnect in 5 seconds
        setTimeout(initWebSocket, 5000);
    };

    ws.onerror = function(error) {
        console.error('WebSocket error:', error);
    };
}

function syncTimerState(state) {
    if (!isLocalUpdate) {
        clearInterval(timerInterval);
        currentLevel = state.currentLevel;
        timeLeft = state.timeLeft;
        isRunning = state.isRunning;
        
        if (state.blindLevels && state.blindLevels.length > 0) {
            blindLevels = state.blindLevels;
        }
        
        if (isRunning) {
            startTimer(false); // false means don't broadcast
        }
        
        updateDisplay();
        updateButtonStates();
    }
    isLocalUpdate = false;
}

function saveTimerState() {
    if (ws && ws.readyState === WebSocket.OPEN) {
        isLocalUpdate = true;
        ws.send(JSON.stringify({
            type: 'update',
            data: {
                currentLevel,
                timeLeft,
                isRunning,
                blindLevels
            }
        }));
    }
}

wss.on('connection', function connection(ws) {
    console.log('New client connected');
    
    // Send current state to new client
    ws.send(JSON.stringify({
        type: 'sync',
        data: timerState
    }));

    ws.on('message', function incoming(message) {
        try {
            const data = JSON.parse(message);
            if (data.type === 'update') {
                // Update server state
                timerState = {
                    ...data.data,
                    lastUpdate: Date.now()
                };
                
                // Broadcast to ALL clients including sender
                wss.clients.forEach(function each(client) {
                    if (client.readyState === WebSocket.OPEN) {
                        client.send(JSON.stringify({
                            type: 'sync',
                            data: timerState
                        }));
                    }
                });
            }
        } catch (e) {
            console.error('Error processing message:', e);
        }
    });

    // Handle disconnection
    ws.on('close', () => {
        console.log('Client disconnected');
    });
});