import assert from 'node:assert/strict';
import test from 'node:test';
import { renderAssistantMarkdown } from '../resources/js/Pages/Admin/AiAssistant/markdown.js';

test('assistant Markdown renders common formatting without executable HTML or links', () => {
    const rendered = renderAssistantMarkdown('# Tour\n\n**Duration:** *1 Day*\n\n- Agra\n\n[Safe](https://example.com)\n\n`code`\n\n```js\nconst day = 1;\n```\n\n<script>alert(1)</script>\n\n[Unsafe](javascript:alert(1))');

    assert.match(rendered, /<h1>Tour<\/h1>/);
    assert.match(rendered, /<strong>Duration:<\/strong> <em>1 Day<\/em>/);
    assert.match(rendered, /<li>Agra<\/li>/);
    assert.match(rendered, /href="https:\/\/example.com"/);
    assert.match(rendered, /<code>code<\/code>/);
    assert.match(rendered, /<pre><code class="language-js">/);
    assert.doesNotMatch(rendered, /<script>|href="javascript:/);
    assert.match(rendered, /&lt;script&gt;/);
});
