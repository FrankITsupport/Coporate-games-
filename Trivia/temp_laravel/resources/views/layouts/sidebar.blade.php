@php
    $currentGame = request()->route('game')
        ?? request()->route('question')?->game
        ?? request()->route('answer')?->game
        ?? request()->route('predictionPlayer')?->game
        ?? request()->route('predictionPrompt')?->game;

    $navIcon = function (string $name) {
        return match ($name) {
            'games' => '<path stroke-linecap="round" stroke-linejoin="round" d="M3 9.75 12 3l9 6.75V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1V9.75Z" />',
            'overview' => '<path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3.75h6v6h-6zM14.25 3.75h6v6h-6zM3.75 14.25h6v6h-6zM14.25 14.25h6v6h-6z" />',
            'control' => '<circle cx="12" cy="12" r="8.25" stroke-linecap="round" /><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 9 15 12l-4.5 3v-6Z" />',
            'questions' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9.529 8.25c.34-1.007 1.404-1.75 2.65-1.75 1.573 0 2.847 1.007 2.847 2.25 0 1.006-.673 1.86-1.706 2.148-.87.243-1.62.936-1.62 1.852v.5M12 17.25h.008v.008H12v-.008Z" /><circle cx="12" cy="12" r="8.25" stroke-linecap="round" />',
            'players' => '<circle cx="9" cy="8.25" r="2.75" stroke-linecap="round" /><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 19a5.25 5.25 0 0 1 10.5 0M15 8.75a2.5 2.5 0 1 1 2.665 3.995M17 14.5c2.21.312 3.75 1.402 3.75 2.833" />',
            'review' => '<path stroke-linecap="round" stroke-linejoin="round" d="m8.25 12 2.5 2.5 5-5M12 3.75l7.5 3v5.25c0 4.5-3.15 7.31-7.5 8.25-4.35-.94-7.5-3.75-7.5-8.25V6.75l7.5-3Z" />',
            'roster' => '<circle cx="8.25" cy="8" r="2.5" stroke-linecap="round" /><circle cx="16" cy="8.5" r="2" stroke-linecap="round" /><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 19a4.75 4.75 0 0 1 9 0M13.5 19a4 4 0 0 1 7 0" />',
            'settings' => '<circle cx="12" cy="12" r="3" stroke-linecap="round" /><path stroke-linecap="round" stroke-linejoin="round" d="M19.4 13.5a7.97 7.97 0 0 0 0-3l1.9-1.3-1.5-2.6-2.2.6a8 8 0 0 0-2.6-1.5l-.4-2.2h-3l-.4 2.2a8 8 0 0 0-2.6 1.5l-2.2-.6-1.5 2.6L6.6 10.5a7.97 7.97 0 0 0 0 3l-1.9 1.3 1.5 2.6 2.2-.6a8 8 0 0 0 2.6 1.5l.4 2.2h3l.4-2.2a8 8 0 0 0 2.6-1.5l2.2.6 1.5-2.6-1.9-1.3Z" />',
            'prompts' => '<path stroke-linecap="round" stroke-linejoin="round" d="M4.5 6.75h15M4.5 12h15M4.5 17.25h9" />',
            'results' => '<path stroke-linecap="round" stroke-linejoin="round" d="M4.5 19.5V9.75M10.5 19.5V4.5M16.5 19.5v-7.5M3 19.5h18" />',
            default => '<circle cx="12" cy="12" r="3" />',
        };
    };

    $navItem = function (string $routePattern, string $routeName, string $label, string $icon) use ($navIcon, $currentGame) {
        $active = request()->routeIs($routePattern);
        $base = 'group flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition';
        $classes = $active
            ? $base . ' bg-brand-lime/15 text-white'
            : $base . ' text-brand-muted hover:bg-white/5 hover:text-white';
        $iconClasses = $active ? 'text-brand-lime' : 'text-brand-muted group-hover:text-brand-lime';

        return '<a href="' . e(route($routeName, ['game' => $currentGame])) . '" class="' . $classes . '">'
            . '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" class="h-5 w-5 shrink-0 ' . $iconClasses . '">' . $navIcon($icon) . '</svg>'
            . '<span class="truncate">' . e($label) . '</span>'
            . '</a>';
    };
@endphp

<div class="brand-stadium-bg relative flex h-full w-72 flex-col">
    <div class="brand-crowd-dots"></div>

    <div class="relative z-10 flex items-center gap-3 px-6 py-6">
        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white/10 text-lg font-black text-brand-lime ring-1 ring-white/15">
            IO
        </div>
        <div class="leading-tight">
            <p class="text-sm font-bold tracking-wide text-white">GAME IKO ON</p>
            <p class="text-[11px] font-medium uppercase tracking-widest text-brand-muted">Admin Console</p>
        </div>
    </div>

    <nav class="relative z-10 flex-1 space-y-6 overflow-y-auto px-4 pb-6">
        <div class="space-y-1">
            <a href="{{ route('admin.games.index') }}"
                class="group flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition {{ request()->routeIs('admin.games.index') || request()->routeIs('admin.games.create') ? 'bg-brand-lime/15 text-white' : 'text-brand-muted hover:bg-white/5 hover:text-white' }}">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"
                    class="h-5 w-5 shrink-0 {{ request()->routeIs('admin.games.index') || request()->routeIs('admin.games.create') ? 'text-brand-lime' : 'text-brand-muted group-hover:text-brand-lime' }}">
                    {!! $navIcon('games') !!}
                </svg>
                <span>All Games</span>
            </a>
        </div>

        @if ($currentGame)
            <div>
                <p class="px-3 text-[11px] font-bold uppercase tracking-widest text-brand-muted/70">
                    {{ Illuminate\Support\Str::limit($currentGame->name, 22) }}
                </p>
                <div class="mt-2 space-y-1">
                    {!! $navItem('admin.games.show', 'admin.games.show', 'Overview', 'overview') !!}
                    {!! $navItem('admin.games.control', 'admin.games.control', 'Control Room', 'control') !!}
                    {!! $navItem('admin.games.questions.*', 'admin.games.questions.index', 'Questions', 'questions') !!}
                    {!! $navItem('admin.games.players.*', 'admin.games.players.index', 'Players', 'players') !!}
                    {!! $navItem('admin.games.review.*', 'admin.games.review.index', 'Review Answers', 'review') !!}
                </div>
            </div>

            <div>
                <p class="px-3 text-[11px] font-bold uppercase tracking-widest text-brand-muted/70">Fan Predictions</p>
                <div class="mt-2 space-y-1">
                    {!! $navItem('admin.games.roster.*', 'admin.games.roster.index', 'Roster', 'roster') !!}
                    {!! $navItem('admin.games.predictions.settings', 'admin.games.predictions.settings', 'Match Settings', 'settings') !!}
                    {!! $navItem('admin.games.prediction-prompts.*', 'admin.games.prediction-prompts.index', 'Prompts', 'prompts') !!}
                </div>
            </div>

            <div>
                <p class="px-3 text-[11px] font-bold uppercase tracking-widest text-brand-muted/70">Wrap Up</p>
                <div class="mt-2 space-y-1">
                    {!! $navItem('admin.games.results.*', 'admin.games.results.show', 'Results & Export', 'results') !!}
                </div>
            </div>
        @endif
    </nav>

    <div class="relative z-10 border-t border-white/10 px-6 py-4">
        <p class="text-[11px] text-brand-muted">Signed in as</p>
        <p class="truncate text-sm font-semibold text-white">{{ Auth::user()->name }}</p>
    </div>
</div>
