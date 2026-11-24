{* EchoCall client area, module 2.0.0. One template for every page so no include path depends on the WHMCS installation. *}
{assign var="t" value=$ec.t}
<link rel="stylesheet" href="{$WEB_ROOT|escape:'html'}/{$ec.assetBase|escape:'html'}/css/echocall.css?v={$ec.version|escape:'url'}">
<script src="{$WEB_ROOT|escape:'html'}/{$ec.assetBase|escape:'html'}/js/echocall.js?v={$ec.version|escape:'url'}" defer></script>

<section class="ec-root" lang="{$ec.lang|escape:'html'}"
    data-ec-theme="{$ec.theme|escape:'html'}"
    data-ec-scheme="{if $ec.theme == 'dark'}dark{else}light{/if}"
    data-ec-endpoint="{$ec.baseUrl|escape:'html'}&amp;ecpage={$ec.page|escape:'url'}"
    data-ec-token="{$ec.actionToken|escape:'html'}"
    data-ec-whmcs-token="{$ec.whmcsToken|escape:'html'}"
    data-ec-msg-error="{$t.error_generic|escape:'html'}"
    data-ec-msg-session="{$t.error_session|escape:'html'}"
    data-ec-msg-copied="{$t.copied|escape:'html'}"
    aria-labelledby="ec-title">

    <header class="ec-header">
        <div class="ec-header-text">
            <h2 class="ec-title" id="ec-title">{$ec.title|escape:'html'}</h2>
            <p class="ec-subtitle">{$t.subtitle|escape:'html'}</p>
        </div>
        {if $ec.portalUrl}
            <a class="ec-button ec-button-primary" href="{$ec.portalUrl|escape:'html'}" target="_blank" rel="noopener noreferrer">
                {$t.open_portal|escape:'html'}<span class="ec-sr"> {$t.new_window|escape:'html'}</span>
            </a>
        {/if}
    </header>

    {if $ec.page != 'status'}
        <nav class="ec-nav" aria-label="{$t.nav_label|escape:'html'}">
            <ul>
                {foreach $ec.nav as $item}
                    <li><a href="{$item.url|escape:'html'}"{if $item.active} aria-current="page"{/if}>{$item.label|escape:'html'}</a></li>
                {/foreach}
            </ul>
        </nav>
    {/if}

    <div class="ec-toast" role="status" aria-live="polite" hidden></div>

    {if $ec.error}
        <div class="ec-notice ec-notice-danger" role="alert">
            <p>{$ec.error|escape:'html'}</p>
        </div>
    {/if}

    {* ── Inactive or not linked ───────────────────────────────────────── *}
    {if $ec.page == 'status'}
        {if !$ec.error}
            <div class="ec-empty">
                <h3>{$t.status_inactive_title|escape:'html'}</h3>
                <p>{$t.status_inactive_body|escape:'html'}</p>
            </div>
        {/if}

    {* ── Overview ──────────────────────────────────────────────────────── *}
    {elseif $ec.page == 'overview' && !$ec.error}
        <div class="ec-grid">
            <article class="ec-card ec-balance" aria-labelledby="ec-balance-title">
                <h3 class="ec-card-label" id="ec-balance-title">{$t.balance|escape:'html'}</h3>
                <p class="ec-balance-value">{$ec.balance|escape:'html'}</p>
                {if $ec.balanceLow}
                    <p class="ec-hint ec-hint-warning">{$t.balance_low|escape:'html'}</p>
                {/if}
                {if $ec.topupAmounts}
                    <form class="ec-topup" data-ec-action="topup.create">
                        <fieldset>
                            <legend>{$t.topup_title|escape:'html'}</legend>
                            <div class="ec-chips">
                                {foreach $ec.topupAmounts as $amount}
                                    <button type="submit" name="amount" value="{$amount.value|escape:'html'}" class="ec-chip">{$amount.label|escape:'html'}</button>
                                {/foreach}
                            </div>
                        </fieldset>
                        <p class="ec-hint">{$t.topup_hint|escape:'html'}</p>
                    </form>
                {/if}
            </article>

            <article class="ec-card" aria-labelledby="ec-plan-title">
                <h3 class="ec-card-label" id="ec-plan-title">{$t.plan|escape:'html'}</h3>
                <p class="ec-card-value">{if $ec.planName}{$ec.planName|escape:'html'}{else}{$t.plan_payg|escape:'html'}{/if}</p>
                <dl class="ec-facts">
                    {if $ec.voiceRemaining !== null}
                        <div><dt>{$t.voice_remaining|escape:'html'}</dt><dd>{$ec.voiceRemaining|escape:'html'} {$t.unit_minutes|escape:'html'}</dd></div>
                    {/if}
                    {if $ec.chatRemaining !== null}
                        <div><dt>{$t.chat_remaining|escape:'html'}</dt><dd>{$ec.chatRemaining|escape:'html'} {$t.unit_sessions|escape:'html'}</dd></div>
                    {/if}
                </dl>
                {if $ec.upgradeUrl}
                    <a class="ec-link" href="{$ec.upgradeUrl|escape:'html'}">{$t.upgrade|escape:'html'}</a>
                {/if}
            </article>

            <article class="ec-card" aria-labelledby="ec-usage-title">
                <h3 class="ec-card-label" id="ec-usage-title">{$t.usage_month|escape:'html'}</h3>
                <dl class="ec-facts">
                    <div><dt>{$t.voice_used|escape:'html'}</dt><dd>{$ec.voiceUsed|escape:'html'} {$t.unit_minutes|escape:'html'}</dd></div>
                    <div><dt>{$t.chat_used|escape:'html'}</dt><dd>{$ec.chatUsed|escape:'html'} {$t.unit_sessions|escape:'html'}</dd></div>
                </dl>
            </article>
        </div>

        <ul class="ec-counts" aria-label="{$t.counts_label|escape:'html'}">
            <li><a href="{$ec.baseUrl|escape:'html'}&amp;ecpage=agents"><span class="ec-count">{$ec.agentCount|escape:'html'}</span> {$t.nav_agents|escape:'html'}</a></li>
            <li><a href="{$ec.baseUrl|escape:'html'}&amp;ecpage=chatbots"><span class="ec-count">{$ec.chatbotCount|escape:'html'}</span> {$t.nav_chatbots|escape:'html'}</a></li>
            <li><a href="{$ec.baseUrl|escape:'html'}&amp;ecpage=numbers"><span class="ec-count">{$ec.numberCount|escape:'html'}</span> {$t.nav_numbers|escape:'html'}</a></li>
        </ul>

        {if $ec.topups}
            <section class="ec-section" aria-labelledby="ec-topups-title">
                <h3 id="ec-topups-title">{$t.topups_recent|escape:'html'}</h3>
                <ul class="ec-list">
                    {foreach $ec.topups as $topup}
                        <li class="ec-row">
                            <div class="ec-row-main">
                                <span class="ec-row-title">{$topup.amountLabel|escape:'html'}</span>
                                <span class="ec-row-meta">{$t.invoice|escape:'html'} #{$topup.invoiceId|escape:'html'}</span>
                            </div>
                            <span class="ec-pill ec-pill-{if $topup.status == 'booked'}success{elseif $topup.status == 'booking'}neutral{else}warning{/if}">
                                {if $topup.status == 'booked'}{$t.topup_booked|escape:'html'}{elseif $topup.status == 'booking'}{$t.topup_booking|escape:'html'}{else}{$t.topup_pending|escape:'html'}{/if}
                            </span>
                            {if $topup.status == 'pending'}
                                <a class="ec-button" href="viewinvoice.php?id={$topup.invoiceId|escape:'url'}">{$t.pay_invoice|escape:'html'}</a>
                            {/if}
                        </li>
                    {/foreach}
                </ul>
            </section>
        {/if}

    {* ── Voice agents ──────────────────────────────────────────────────── *}
    {elseif $ec.page == 'agents' && !$ec.error}
        <section class="ec-section" aria-labelledby="ec-agents-title">
            <div class="ec-section-head">
                <h3 id="ec-agents-title">{$t.nav_agents|escape:'html'}</h3>
                <p class="ec-hint">{$t.agents_hint|escape:'html'}</p>
            </div>
            {if $ec.agents}
                <ul class="ec-list">
                    {foreach $ec.agents as $agent}
                        <li class="ec-row">
                            <div class="ec-row-main">
                                <span class="ec-row-title">{$agent.name|escape:'html'}</span>
                                <span class="ec-row-meta">{if $agent.language}{$agent.language|upper|escape:'html'}{/if}{if $agent.voice.name} · {$agent.voice.name|escape:'html'}{/if}</span>
                            </div>
                            <span class="ec-pill ec-pill-{if $agent.status == 'active'}success{else}neutral{/if}">{if $agent.status == 'active'}{$t.status_active|escape:'html'}{else}{$t.status_paused|escape:'html'}{/if}</span>
                            <div class="ec-row-actions">
                                <a class="ec-button" href="{$ec.baseUrl|escape:'html'}&amp;ecpage=knowledge&amp;kind=agent&amp;ref={$agent.id|escape:'url'}">{$t.knowledge|escape:'html'}<span class="ec-sr"> {$agent.name|escape:'html'}</span></a>
                                <form data-ec-action="agent.status">
                                    <input type="hidden" name="id" value="{$agent.id|escape:'html'}">
                                    <input type="hidden" name="status" value="{if $agent.status == 'active'}inactive{else}active{/if}">
                                    <button type="submit" class="ec-button">{if $agent.status == 'active'}{$t.pause|escape:'html'}{else}{$t.activate|escape:'html'}{/if}<span class="ec-sr"> {$agent.name|escape:'html'}</span></button>
                                </form>
                            </div>
                        </li>
                    {/foreach}
                </ul>
            {else}
                <div class="ec-empty">
                    <h4>{$t.agents_empty_title|escape:'html'}</h4>
                    <p>{$t.agents_empty_body|escape:'html'}</p>
                </div>
            {/if}
        </section>

    {* ── Chatbots ──────────────────────────────────────────────────────── *}
    {elseif $ec.page == 'chatbots' && !$ec.error}
        <section class="ec-section" aria-labelledby="ec-chatbots-title">
            <div class="ec-section-head">
                <h3 id="ec-chatbots-title">{$t.nav_chatbots|escape:'html'}</h3>
                <p class="ec-hint">{$t.chatbots_hint|escape:'html'}</p>
            </div>
            {if $ec.chatbots}
                <ul class="ec-list">
                    {foreach $ec.chatbots as $bot}
                        <li class="ec-row ec-row-wrap">
                            <div class="ec-row-main">
                                <span class="ec-row-title">{$bot.name|escape:'html'}</span>
                                <span class="ec-row-meta">{$t.chatbot_id|escape:'html'} {$bot.id|escape:'html'}{if $bot.language} · {$bot.language|upper|escape:'html'}{/if}</span>
                            </div>
                            <span class="ec-pill ec-pill-{if $bot.status == 'active'}success{else}neutral{/if}">{if $bot.status == 'active'}{$t.status_active|escape:'html'}{else}{$t.status_paused|escape:'html'}{/if}</span>
                            <div class="ec-row-actions">
                                <form data-ec-action="chatbot.embed" data-ec-target="ec-embed-{$bot.id|escape:'html'}">
                                    <input type="hidden" name="id" value="{$bot.id|escape:'html'}">
                                    <button type="submit" class="ec-button">{$t.embed_code|escape:'html'}<span class="ec-sr"> {$bot.name|escape:'html'}</span></button>
                                </form>
                                <a class="ec-button" href="{$ec.baseUrl|escape:'html'}&amp;ecpage=knowledge&amp;kind=chatbot&amp;ref={$bot.id|escape:'url'}">{$t.knowledge|escape:'html'}<span class="ec-sr"> {$bot.name|escape:'html'}</span></a>
                                <form data-ec-action="chatbot.status">
                                    <input type="hidden" name="id" value="{$bot.id|escape:'html'}">
                                    <input type="hidden" name="status" value="{if $bot.status == 'active'}inactive{else}active{/if}">
                                    <button type="submit" class="ec-button">{if $bot.status == 'active'}{$t.pause|escape:'html'}{else}{$t.activate|escape:'html'}{/if}<span class="ec-sr"> {$bot.name|escape:'html'}</span></button>
                                </form>
                            </div>
                            <div class="ec-embed" id="ec-embed-{$bot.id|escape:'html'}" hidden>
                                <label for="ec-embed-code-{$bot.id|escape:'html'}">{$t.embed_label|escape:'html'}</label>
                                <textarea id="ec-embed-code-{$bot.id|escape:'html'}" rows="3" readonly spellcheck="false"></textarea>
                                <button type="button" class="ec-button" data-ec-copy="ec-embed-code-{$bot.id|escape:'html'}">{$t.copy|escape:'html'}</button>
                            </div>
                        </li>
                    {/foreach}
                </ul>
            {else}
                <div class="ec-empty">
                    <h4>{$t.chatbots_empty_title|escape:'html'}</h4>
                    <p>{$t.chatbots_empty_body|escape:'html'}</p>
                </div>
            {/if}
        </section>

    {* ── Phone numbers ─────────────────────────────────────────────────── *}
    {elseif $ec.page == 'numbers' && !$ec.error}
        <section class="ec-section" aria-labelledby="ec-numbers-title">
            <div class="ec-section-head">
                <h3 id="ec-numbers-title">{$t.nav_numbers|escape:'html'}</h3>
                <p class="ec-hint">{$t.numbers_hint|escape:'html'}</p>
            </div>
            {if $ec.numbers}
                <ul class="ec-list">
                    {foreach $ec.numbers as $number}
                        <li class="ec-row ec-row-wrap">
                            <div class="ec-row-main">
                                <span class="ec-row-title ec-tabular">{$number.phoneNumber|escape:'html'}</span>
                                <span class="ec-row-meta">{if $number.friendlyName}{$number.friendlyName|escape:'html'}{else}{$t.no_label|escape:'html'}{/if}</span>
                            </div>
                            <form class="ec-inline" data-ec-action="number.assign">
                                <input type="hidden" name="id" value="{$number.id|escape:'html'}">
                                <label for="ec-assign-{$number.id|escape:'html'}">{$t.answered_by|escape:'html'}</label>
                                <select id="ec-assign-{$number.id|escape:'html'}" name="agent">
                                    <option value="">{$t.no_agent|escape:'html'}</option>
                                    {foreach $ec.assignable as $option}
                                        <option value="{$option.id|escape:'html'}"{if $option.id == $number.agentRef} selected{/if}>{$option.name|escape:'html'}</option>
                                    {/foreach}
                                </select>
                                <button type="submit" class="ec-button">{$t.save|escape:'html'}</button>
                            </form>
                            <form class="ec-inline" data-ec-action="number.label">
                                <input type="hidden" name="id" value="{$number.id|escape:'html'}">
                                <label for="ec-label-{$number.id|escape:'html'}">{$t.label|escape:'html'}</label>
                                <input id="ec-label-{$number.id|escape:'html'}" type="text" name="label" maxlength="100" autocomplete="off" value="{$number.friendlyName|escape:'html'}">
                                <button type="submit" class="ec-button">{$t.save|escape:'html'}</button>
                            </form>
                        </li>
                    {/foreach}
                </ul>
            {else}
                <div class="ec-empty">
                    <h4>{$t.numbers_empty_title|escape:'html'}</h4>
                    <p>{$t.numbers_empty_body|escape:'html'}</p>
                </div>
            {/if}
        </section>

    {* ── Knowledge of one agent or chatbot ─────────────────────────────── *}
    {elseif $ec.page == 'knowledge' && !$ec.error}
        <section class="ec-section" aria-labelledby="ec-knowledge-title">
            <a class="ec-link ec-back" href="{$ec.baseUrl|escape:'html'}&amp;ecpage={if $ec.kind == 'chatbot'}chatbots{else}agents{/if}">{if $ec.kind == 'chatbot'}{$t.back_chatbots|escape:'html'}{else}{$t.back_agents|escape:'html'}{/if}</a>
            <div class="ec-section-head">
                <h3 id="ec-knowledge-title">{$t.knowledge_of|escape:'html'} {$ec.ownerName|escape:'html'}</h3>
                <p class="ec-hint">{$t.knowledge_hint|escape:'html'}</p>
            </div>
            <form class="ec-inline ec-inline-wide" data-ec-action="knowledge.add">
                <input type="hidden" name="kind" value="{$ec.kind|escape:'html'}">
                <input type="hidden" name="id" value="{$ec.ref|escape:'html'}">
                <label for="ec-knowledge-url">{$t.knowledge_url|escape:'html'}</label>
                <input id="ec-knowledge-url" type="url" name="url" required maxlength="2048" inputmode="url" autocomplete="url" placeholder="https://">
                <button type="submit" class="ec-button ec-button-primary">{$t.add|escape:'html'}</button>
            </form>
            {if $ec.items}
                <ul class="ec-list">
                    {foreach $ec.items as $item}
                        <li class="ec-row">
                            <div class="ec-row-main">
                                <span class="ec-row-title ec-break">{if $item.label}{$item.label|escape:'html'}{else}{$t.knowledge_untitled|escape:'html'}{/if}</span>
                                <span class="ec-row-meta">{if $item.type == 'url' || $item.type == 'crawl'}{$t.type_url|escape:'html'}{elseif $item.type == 'file'}{$t.type_file|escape:'html'}{else}{$t.type_text|escape:'html'}{/if}</span>
                            </div>
                            <span class="ec-pill ec-pill-{if $item.status == 'ready'}success{elseif $item.status == 'failed'}danger{else}neutral{/if}">{if $item.status == 'ready'}{$t.knowledge_ready|escape:'html'}{elseif $item.status == 'failed'}{$t.knowledge_failed|escape:'html'}{else}{$t.knowledge_pending|escape:'html'}{/if}</span>
                            <form data-ec-action="knowledge.delete" data-ec-confirm="{$t.knowledge_delete_confirm|escape:'html'}">
                                <input type="hidden" name="kind" value="{$ec.kind|escape:'html'}">
                                <input type="hidden" name="id" value="{$ec.ref|escape:'html'}">
                                <input type="hidden" name="item" value="{$item.id|escape:'html'}">
                                <button type="submit" class="ec-button ec-button-danger">{$t.delete|escape:'html'}</button>
                            </form>
                        </li>
                    {/foreach}
                </ul>
            {else}
                <div class="ec-empty">
                    <h4>{$t.knowledge_empty_title|escape:'html'}</h4>
                    <p>{$t.knowledge_empty_body|escape:'html'}</p>
                </div>
            {/if}
        </section>

    {* ── Integrations ──────────────────────────────────────────────────── *}
    {elseif $ec.page == 'integrations' && !$ec.error}
        <section class="ec-section" aria-labelledby="ec-integrations-title">
            <div class="ec-section-head">
                <h3 id="ec-integrations-title">{$t.nav_integrations|escape:'html'}</h3>
                <p class="ec-hint">{$t.integrations_hint|escape:'html'}</p>
            </div>
            {if $ec.integrations}
                <ul class="ec-list">
                    {foreach $ec.integrations as $integration}
                        <li class="ec-row">
                            <div class="ec-row-main">
                                <span class="ec-row-title">{$integration.name|escape:'html'}</span>
                                <span class="ec-row-meta">{$integration.type|escape:'html'}</span>
                            </div>
                            <span class="ec-pill ec-pill-{if $integration.isActive}success{else}neutral{/if}">{if $integration.isActive}{$t.status_connected|escape:'html'}{else}{$t.status_off|escape:'html'}{/if}</span>
                        </li>
                    {/foreach}
                </ul>
            {else}
                <div class="ec-empty">
                    <h4>{$t.integrations_empty_title|escape:'html'}</h4>
                    <p>{$t.integrations_empty_body|escape:'html'}</p>
                </div>
            {/if}
        </section>

    {* ── WordPress plugin ──────────────────────────────────────────────── *}
    {elseif $ec.page == 'wordpress' && !$ec.error}
        <section class="ec-section" aria-labelledby="ec-wordpress-title">
            <div class="ec-section-head">
                <h3 id="ec-wordpress-title">{$t.wordpress_title|escape:'html'}</h3>
                <p class="ec-hint">{$t.wordpress_hint|escape:'html'}</p>
            </div>
            <ol class="ec-steps">
                <li>{$t.wordpress_step1|escape:'html'}</li>
                <li>{$t.wordpress_step2|escape:'html'}</li>
                <li>{$t.wordpress_step3|escape:'html'}</li>
                <li>{$t.wordpress_step4|escape:'html'}</li>
            </ol>
            <a class="ec-button ec-button-primary" href="{if $ec.pluginLocal}{$WEB_ROOT|escape:'html'}/{/if}{$ec.pluginUrl|escape:'html'}" download>{$t.wordpress_download|escape:'html'}</a>
            <p class="ec-hint">{$t.wordpress_requirements|escape:'html'}</p>
            <h4 class="ec-subhead">{$t.wordpress_chatbots|escape:'html'}</h4>
            {if $ec.chatbots}
                <p class="ec-hint">{$t.wordpress_chatbots_hint|escape:'html'}</p>
                <ul class="ec-list">
                    {foreach $ec.chatbots as $bot}
                        <li class="ec-row ec-row-wrap">
                            <div class="ec-row-main">
                                <span class="ec-row-title">{$bot.name|escape:'html'}</span>
                            </div>
                            <span class="ec-pill ec-pill-{if $bot.status == 'active'}success{else}neutral{/if}">{if $bot.status == 'active'}{$t.status_active|escape:'html'}{else}{$t.status_paused|escape:'html'}{/if}</span>
                            <code class="ec-code">[echocall_chat id=&quot;{$bot.id|escape:'html'}&quot;]</code>
                        </li>
                    {/foreach}
                </ul>
            {else}
                <p class="ec-hint">{$t.wordpress_empty|escape:'html'}</p>
            {/if}
        </section>
    {/if}

    <footer class="ec-footer">
        <p>{$t.footer_portal|escape:'html'}</p>
    </footer>
</section>
