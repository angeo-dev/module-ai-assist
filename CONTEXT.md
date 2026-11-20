# Context contract

How a module gives its page to the AI assistant. No code dependency in
either direction: an event and a layout file.

## 1. Answer the event

```xml
<!-- your-module/etc/adminhtml/events.xml -->
<event name="angeo_ai_assist_context">
    <observer name="your_module_ai_context" instance="Vendor\Module\Observer\AiAssistContext"/>
</event>
```

Event data:

| Key | Type | Meaning |
|---|---|---|
| `source` | string | Which page. Answer only your own source code. |
| `ref` | string | The record id, or `a=1&b=2` filters (max 64 chars). |
| `context` | `DataObject` | Write your answer here. |

Write into `context`:

| Key | Type | Required | Meaning |
|---|---|---|---|
| `facts` | array | yes | What the page shows. Arrays and scalars only. |
| `title` | string | yes | "AEO audit #42, default store" |
| `summary` | string | no | One or two sentences: what this page measures. |
| `glossary` | array<string,string> | no | Terms the model should read your way. |
| `denied` | bool | no | Set `true` when the admin may not see this record. |

Rules for observers:

- **Check your own ACL** (`AuthorizationInterface::isAllowed`) and set
  `denied` when it fails. The assistant has its own ACL, but your data is
  yours to guard.
- **Load by `ref` yourself.** Never trust anything else from the request.
- Send what the page shows, not more. No customer data.
- Do nothing when `source` is not yours.

## 2. Place the panel

The assistant module carries the layout files for the Angeo pages. Another
module adds its own, in the assistant module's style:

```xml
<referenceContainer name="content">
    <block class="Angeo\AiAssist\Block\Adminhtml\Panel" name="angeo.ai.assist.panel" after="-">
        <arguments>
            <argument name="source" xsi:type="string">your_source</argument>
            <argument name="ref_param" xsi:type="string">id</argument>
        </arguments>
    </block>
</referenceContainer>
```

Arguments: `ref_param` (one request parameter), `ref_params` (a comma list
of filter parameters), or `ref_event` = `1` when the page loads its record in
the browser. Then announce it:

```js
$(document).trigger('angeo-ai-assist:ref', {source: 'your_source', ref: '42'});
```
