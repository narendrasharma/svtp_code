import { micromark } from 'micromark';

export function renderAssistantMarkdown(content) {
    return micromark(String(content ?? ''), {
        allowDangerousHtml: false,
        allowDangerousProtocol: false,
    });
}
