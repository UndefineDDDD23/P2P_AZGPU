class VideoConference {
    constructor(wsUrl) {
        // --- DOM элементы ---
        this.localVideo = document.getElementById("localVideo");
        this.remoteVideosContainer = document.getElementById("remoteVideosContainer");
        this.roomIdInput = document.getElementById("roomIdInput");
        this.shareScreenButton = document.getElementById("shareScreen");
        this.toggleCameraButton = document.getElementById("toggleCamera");
        this.toggleMicButton = document.getElementById("toggleMic");

        // --- Состояние ---
        this.roomId = null;
        this.peerConnections = {};
        this.localStream = null;
        this.screenStream = null;
        this.isCameraEnabled = true;
        this.isMicEnabled = true;

        // --- WebSocket ---
        this.socket = new WebSocket(wsUrl);
        this.socket.onmessage = (event) => this.onSocketMessage(event);

        // --- Привязка событий ---
        this.shareScreenButton.onclick = () => this.shareScreen();
        this.toggleCameraButton.onclick = () => this.toggleCamera();
        this.toggleMicButton.onclick = () => this.toggleMic();
    }

    // --- Утилита для чтения query параметров ---
    getQueryParam(param) {
        const urlParams = new URLSearchParams(window.location.search);
        return urlParams.get(param);
    }

    // --- Обработка сообщений WebSocket ---
    onSocketMessage(event) {
        const data = JSON.parse(event.data);
        switch (data.type) {
            case 'new-peer':
                this.handleNewPeer(data.peerId);
                break;
            case 'signal':
                this.handleSignal(data.peerId, data.signalData);
                break;
            case 'peer-left':
                this.handlePeerLeft(data.peerId);
                break;
            case 'room-created':
                let roomUrlElement = document.getElementById("room_URL");
                roomUrlElement.href = data.url;
                roomUrlElement.innerText = data.url;
                console.log(`Комната создана!\nRoom ID: ${data.roomId}\nКлюч: ${data.secretKey}\nURL: ${data.url}`);
                break;
            default:
                console.log("Неизвестный тип сообщения:", data.type);
        }
    }

    // --- Присоединение к комнате ---
    async joinRoom() {
        this.roomId = this.roomIdInput.value || this.getQueryParam('roomId');
        const key = this.getQueryParam('key');
        if (!this.roomId || !key) {
            alert("Для подключения к комнате в URL должны быть заданы параметры roomId и key.");
            return;
        }

        try {
            this.localStream = await navigator.mediaDevices.getUserMedia({ video: true, audio: true });
            this.localVideo.srcObject = this.localStream;
            console.log(`Local video track: ${this.localStream.getVideoTracks()[0]?.id}`);
            this.socket.send(JSON.stringify({ type: "join-room", roomId: this.roomId, key }));
        } catch (error) {
            console.error("Ошибка получения локального видео/аудио:", error);
        }
    }

    // --- Новый peer ---
    handleNewPeer(peerId) {
        let pc = this.peerConnections[peerId];
        if (!pc) {
            pc = this.createPeerConnection(peerId);
            this.peerConnections[peerId] = pc;
        }
        this.localStream.getTracks().forEach(track => pc.addTrack(track, this.localStream));

        pc.createOffer()
            .then(offer => {
                pc.setLocalDescription(offer);
                this.socket.send(JSON.stringify({
                    type: "signal",
                    roomId: this.roomId,
                    targetId: peerId,
                    signalData: offer
                }));
            })
            .catch(err => console.error("Ошибка создания offer:", err));
    }

    // --- Обработка сигналинга ---
    handleSignal(peerId, signalData) {
        let pc = this.peerConnections[peerId];
        if (!pc) {
            pc = this.createPeerConnection(peerId);
            this.peerConnections[peerId] = pc;
        }

        if (signalData.type === "offer") {
            pc.setRemoteDescription(new RTCSessionDescription(signalData));
            this.localStream.getTracks().forEach(track => pc.addTrack(track, this.localStream));
            pc.createAnswer()
                .then(answer => {
                    pc.setLocalDescription(answer);
                    this.socket.send(JSON.stringify({
                        type: "signal",
                        roomId: this.roomId,
                        targetId: peerId,
                        signalData: answer
                    }));
                })
                .catch(err => console.error("Ошибка создания answer:", err));
        } else if (signalData.type === "answer") {
            pc.setRemoteDescription(new RTCSessionDescription(signalData))
                .then(() => console.log(`✅ Remote Description установлено для ${peerId}`))
                .catch(err => console.error("Ошибка setRemoteDescription:", err));
        } else if (signalData.candidate) {
            pc.addIceCandidate(new RTCIceCandidate(signalData))
                .catch(err => console.error("Ошибка добавления ICE-кандидата:", err));
        }
    }

    // --- Peer отключился ---
    handlePeerLeft(peerId) {
        const remoteVideo = document.getElementById(`remote-video-${peerId}`);
        if (remoteVideo) remoteVideo.remove();

        if (this.peerConnections[peerId]) {
            this.peerConnections[peerId].close();
            delete this.peerConnections[peerId];
        }
    }

    // --- Создание нового RTCPeerConnection ---
    createPeerConnection(peerId) {
        const configuration = {
            iceServers: [
                { 'url': 'stun:stun.l.google.com:19302' },
                {
                    'url': 'turn:192.158.29.39:3478?transport=udp',
                    'credential': 'JZEOEt2V3Qb0y27GRntt2u2PAYA=',
                    'username': '28224511:1379330808'
                },
                {
                    'url': 'turn:192.158.29.39:3478?transport=tcp',
                    'credential': 'JZEOEt2V3Qb0y27GRntt2u2PAYA=',
                    'username': '28224511:1379330808'
                }
            ]
        };
        const pc = new RTCPeerConnection(configuration);

        pc.ontrack = (event) => {
            if (event.track.kind === "video") {
                const remoteVideo = document.createElement("video");
                remoteVideo.id = `remote-video-${peerId}`;
                remoteVideo.srcObject = event.streams[0];
                remoteVideo.autoplay = true;
                this.remoteVideosContainer.appendChild(remoteVideo);
            }
            console.log(`Получен трек ${event.track.kind} от ${peerId}: ${event.track.id}`);
        };

        pc.onicecandidate = (event) => {
            if (event.candidate) {
                this.socket.send(JSON.stringify({
                    type: "signal",
                    roomId: this.roomId,
                    targetId: peerId,
                    signalData: event.candidate
                }));
            }
        };

        return pc;
    }

    // --- Демонстрация экрана ---
    async shareScreen() {
        try {
            this.screenStream = await navigator.mediaDevices.getDisplayMedia({ video: true });
            const screenTrack = this.screenStream.getTracks()[0];
            Object.values(this.peerConnections).forEach(pc => {
                const sender = pc.getSenders().find(s => s.track.kind === "video");
                if (sender) sender.replaceTrack(screenTrack);
            });
            screenTrack.onended = () => this.stopScreenShare();
        } catch (error) {
            console.error("Ошибка демонстрации экрана:", error);
        }
    }

    stopScreenShare() {
        if (this.screenStream) {
            const videoTrack = this.localStream.getVideoTracks()[0];
            Object.values(this.peerConnections).forEach(pc => {
                const sender = pc.getSenders().find(s => s.track.kind === "video");
                if (sender) sender.replaceTrack(videoTrack);
            });
            this.screenStream.getTracks().forEach(track => track.stop());
            this.screenStream = null;
        }
    }

    toggleCamera() {
        this.isCameraEnabled = !this.isCameraEnabled;
        this.localStream.getVideoTracks()[0].enabled = this.isCameraEnabled;
    }

    toggleMic() {
        this.isMicEnabled = !this.isMicEnabled;
        this.localStream.getAudioTracks()[0].enabled = this.isMicEnabled;
    }

    leaveRoom() {
        if (!this.roomId) return;
        this.socket.send(JSON.stringify({ type: "leave-room", roomId: this.roomId }));
        if (this.localStream) this.localStream.getTracks().forEach(track => track.stop());
        Object.values(this.peerConnections).forEach(pc => pc.close());
        this.peerConnections = {};
        this.roomId = null;
    }

    createRoom() {
        const adminPassword = document.getElementById("adminPasswordInput").value;
        if (!adminPassword) {
            alert("Введите пароль администратора!");
            return;
        }
        this.socket.send(JSON.stringify({ type: "create-room", adminPassword }));
    }
}

// --- Инициализация ---
const conference = new VideoConference("ws://localhost:8080/");

window.joinRoom = () => conference.joinRoom();
window.leaveRoom = () => conference.leaveRoom();
window.createRoom = () => conference.createRoom();
