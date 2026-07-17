<x-event-layout :title="'Join ' . $game->name">
    <div class="mb-6 flex items-center gap-3">
        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-white/10 text-lg font-black text-brand-lime ring-1 ring-white/15">
            IO
        </div>
        <div class="leading-tight">
            <p class="text-sm font-bold tracking-wide text-white">GAME IKO ON</p>
            <p class="text-[11px] font-medium uppercase tracking-widest text-brand-muted">Powered by M-PESA GlobalPay Visa</p>
        </div>
    </div>

    <div class="overflow-hidden rounded-2xl bg-white/95 text-gray-900 shadow-2xl ring-1 ring-black/5 backdrop-blur">
        <div class="h-1.5 w-full bg-gradient-to-r from-brand-green via-brand-lime to-brand-visa"></div>
        <div class="p-6">
            <h1 class="text-xl font-bold">{{ $game->name }}</h1>
            <p class="mt-1 text-sm text-gray-500">Enter your details to join.</p>

            @if ($errors->any())
                <div class="mt-4 rounded-lg bg-red-50 p-3 text-sm text-red-700 ring-1 ring-inset ring-red-200">
                    <ul class="list-disc space-y-1 pl-4">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('join.store', $game) }}" class="mt-6 space-y-4">
                @csrf

                <div>
                    <label for="nickname" class="block text-sm font-medium text-gray-700">Nickname</label>
                    <input id="nickname" type="text" name="nickname" value="{{ old('nickname') }}" required maxlength="50"
                        class="mt-1 w-full rounded-lg border-gray-300 shadow-sm focus:border-brand-green focus:ring-brand-green">
                </div>

                <div>
                    <label for="phone" class="block text-sm font-medium text-gray-700">Mobile number</label>
                    <input id="phone" type="tel" name="phone" value="{{ old('phone') }}" required placeholder="07XXXXXXXX"
                        class="mt-1 w-full rounded-lg border-gray-300 shadow-sm focus:border-brand-green focus:ring-brand-green">
                </div>

                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700">Email (optional)</label>
                    <input id="email" type="email" name="email" value="{{ old('email') }}"
                        class="mt-1 w-full rounded-lg border-gray-300 shadow-sm focus:border-brand-green focus:ring-brand-green">
                </div>

                <label class="flex items-start gap-2 text-sm text-gray-600">
                    <input type="checkbox" name="mpesa_card_confirmed" value="1" class="mt-1 rounded border-gray-300 text-brand-green focus:ring-brand-green" {{ old('mpesa_card_confirmed') ? 'checked' : '' }}>
                    I have an M-PESA GlobalPay Virtual Visa Card
                </label>

                <label class="flex items-start gap-2 text-sm text-gray-600">
                    <input type="checkbox" name="consent_accepted" value="1" required class="mt-1 rounded border-gray-300 text-brand-green focus:ring-brand-green" {{ old('consent_accepted') ? 'checked' : '' }}>
                    I consent to participate, with my nickname and score shown on the stage screen.
                </label>

                <button type="submit"
                    class="w-full rounded-lg bg-brand-green py-2.5 font-semibold text-white shadow-sm transition hover:bg-brand-lime focus:outline-none focus:ring-2 focus:ring-brand-green focus:ring-offset-2">
                    Join Game
                </button>
            </form>
        </div>
    </div>
</x-event-layout>
