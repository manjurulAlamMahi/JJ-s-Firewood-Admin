<footer class="av-footer">
    {{ $systemSettings?->copyright_text
        ?? '© ' . date('Y') . ' ' . ($systemSettings?->site_name ?? "JJ'S Firewood Perth") . '. All rights reserved.' }}
</footer>
