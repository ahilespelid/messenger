/**
 * Thin wrapper around MediaRecorder for capturing a single voice message.
 * Picks whatever codec the browser actually supports (webm/opus on
 * Chrome/Firefox, mp4/aac on Safari) rather than assuming one.
 */
export function createRecorder() {
    let mediaRecorder = null;
    let chunks = [];
    let stream = null;

    function pickMimeType() {
        const candidates = ['audio/webm;codecs=opus', 'audio/webm', 'audio/mp4'];
        return candidates.find((type) => window.MediaRecorder?.isTypeSupported?.(type)) || '';
    }

    return {
        async start() {
            stream = await navigator.mediaDevices.getUserMedia({ audio: true });
            chunks = [];

            const mimeType = pickMimeType();
            mediaRecorder = mimeType ? new MediaRecorder(stream, { mimeType }) : new MediaRecorder(stream);

            mediaRecorder.addEventListener('dataavailable', (e) => {
                if (e.data.size > 0) chunks.push(e.data);
            });

            mediaRecorder.start();
        },

        stop() {
            return new Promise((resolve) => {
                if (!mediaRecorder) return resolve(null);

                mediaRecorder.addEventListener(
                    'stop',
                    () => {
                        const blob = new Blob(chunks, { type: mediaRecorder.mimeType || 'audio/webm' });
                        stream?.getTracks().forEach((t) => t.stop());
                        resolve(blob);
                    },
                    { once: true }
                );

                mediaRecorder.stop();
            });
        },

        cancel() {
            if (mediaRecorder && mediaRecorder.state !== 'inactive') {
                mediaRecorder.stop();
            }
            stream?.getTracks().forEach((t) => t.stop());
            chunks = [];
        },
    };
}
