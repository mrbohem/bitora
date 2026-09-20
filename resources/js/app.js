import { Terminal } from '@xterm/xterm';
import { FitAddon } from '@xterm/addon-fit';
import '@xterm/xterm/css/xterm.css';

class ProjectTerminalClient {
    constructor(element) {
        this.element = element;
        this.projectId = element.dataset.projectId;
        this.terminal = new Terminal({
            cursorBlink: true,
            fontFamily: 'JetBrains Mono, ui-monospace, monospace',
            fontSize: 14,
            theme: {
                background: '#111827',
                foreground: '#d1fae5',
                cursor: '#d0bcff',
            },
        });
        this.fit = new FitAddon();
        this.token = null;
        this.offset = 0;
        this.closed = false;
        this.polling = false;
        this.pollTimer = null;
        this.pollDelay = 50;
        this.pendingInput = '';
        this.inputFlushTimer = null;
        this.inputQueue = Promise.resolve();
        this.outputErrorShown = false;
        this.headers = {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
        };
    }

    start() {
        this.terminal.loadAddon(this.fit);
        this.terminal.open(this.element);
        this.fit.fit();
        this.terminal.onWriteParsed(() => {
            this.interactiveMode = this.terminal.buffer.active.type === 'alternate';

            if (this.interactiveMode) {
                this.clearInputTimer();
                this.flushInput();
            }
        });
        this.terminal.onData((input) => this.queueInput(input));
        this.resize = () => this.fit.fit();
        window.addEventListener('resize', this.resize);
        this.connect();
    }

    async connect() {
        try {
            const payload = await this.request(`/projects/${this.projectId}/terminal`, { method: 'POST' });

            this.token = payload.token;
            this.polling = true;
            this.terminal.focus();
            this.terminal.write('Connected to container.\r\n');
            this.poll();
        } catch (error) {
            this.terminal.write(`\r\n[terminal error] ${error.message}\r\n`);
        }
    }

    queueInput(input) {
        this.pendingInput += input;
        const immediate = this.interactiveMode || input.includes('\x1b');

        if (!this.interactiveMode) {
            this.renderLocalInput(input);
        }

        if (immediate || input.includes('\r') || input.includes('\x03')) {
            this.clearInputTimer();
            this.flushInput();

            return;
        }

        this.clearInputTimer();
        this.inputFlushTimer = window.setTimeout(() => this.flushInput(), 500);
    }

    flushInput() {
        this.inputFlushTimer = null;

        if (!this.pendingInput) {
            return;
        }

        const input = this.pendingInput;
        this.pendingInput = '';
        this.sendInput(input);
    }

    sendInput(input) {
        if (!this.token || !input) {
            return;
        }

        this.inputQueue = this.inputQueue
            .then(() => this.request(`/projects/${this.projectId}/terminal/${this.token}/input`, {
                method: 'POST',
                body: JSON.stringify({ input_base64: this.encodeInput(input) }),
            }))
            .then(() => {
                this.pollDelay = 50;
            })
            .catch((error) => this.terminal.write(`\r\n[input error] ${error.message}\r\n`));
    }

    async poll() {
        if (this.closed || !this.token || !this.polling) {
            return;
        }

        try {
            const payload = await this.request(
                `/projects/${this.projectId}/terminal/${this.token}/output?offset=${this.offset}`,
                { headers: { Accept: 'application/json' } },
            );

            if (payload.data) {
                this.terminal.write(payload.data);
            }

            this.offset = payload.offset;
            this.pollDelay = 50;
            this.outputErrorShown = false;

            if (payload.done) {
                this.polling = false;
                this.terminal.write('\r\n[process exited]\r\n');

                return;
            }
        } catch (error) {
            this.pollDelay = Math.min(this.pollDelay * 2, 2000);

            if (!this.outputErrorShown) {
                this.terminal.write(`\r\n[output error] ${error.message}\r\n`);
                this.outputErrorShown = true;
            }
        }

        if (!this.closed && this.polling) {
            this.pollTimer = window.setTimeout(() => this.poll(), this.pollDelay);
        }
    }

    renderLocalInput(input) {
        let rendered = '';

        for (const character of input) {
            if (character === '\r') {
                rendered += '\r\n';
            } else if (character === '\x7f') {
                rendered += '\b \b';
            } else if (character === '\x03') {
                rendered += '^C\r\n';
            } else if (character === '\t' || (character >= ' ' && character !== '\x7f')) {
                rendered += character;
            }
        }

        if (rendered) {
            this.terminal.write(rendered);
        }
    }

    async request(url, options = {}) {
        const response = await fetch(url, {
            ...options,
            headers: { ...this.headers, ...(options.headers || {}) },
            credentials: 'same-origin',
        });

        if (!response.ok) {
            throw new Error(await response.text());
        }

        return response.json();
    }

    encodeInput(input) {
        const bytes = new TextEncoder().encode(input);
        let binary = '';

        bytes.forEach((byte) => {
            binary += String.fromCharCode(byte);
        });

        return btoa(binary);
    }

    clearInputTimer() {
        if (this.inputFlushTimer) {
            window.clearTimeout(this.inputFlushTimer);
            this.inputFlushTimer = null;
        }
    }

    cleanup() {
        this.closed = true;
        this.polling = false;
        window.removeEventListener('resize', this.resize);
        this.clearInputTimer();

        if (this.pollTimer) {
            window.clearTimeout(this.pollTimer);
        }

        if (this.token) {
            fetch(`/projects/${this.projectId}/terminal/${this.token}`, {
                method: 'DELETE',
                headers: this.headers,
                credentials: 'same-origin',
            });
        }

        this.terminal.dispose();
    }
}

window.initProjectTerminal = function (element) {
    const client = new ProjectTerminalClient(element);
    client.start();
    element._terminalCleanup = () => client.cleanup();
};
