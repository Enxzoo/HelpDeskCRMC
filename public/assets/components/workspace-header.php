<header class="workspace-header">
    <button type="button" class="icon-button workspace-menu" id="workspaceMenuToggle" aria-label="Open navigation" aria-controls="workspaceSidebar" aria-expanded="false" title="Navigation"><img class="icon" src="assets/icons/menu.svg" alt=""></button>
    <div class="workspace-breadcrumb"><span><?= $workspaceRole === 'admin' ? 'Administration' : 'Student portal' ?></span><span aria-hidden="true">/</span><strong><?= htmlspecialchars($workspaceTitle, ENT_QUOTES, 'UTF-8') ?></strong></div>
    <img class="workspace-header-logo" src="assets/images/helpdesk-logo.png" alt="CRMC" width="28" height="28">
</header>
