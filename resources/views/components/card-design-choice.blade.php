@props(['value' => 'classic'])
@if(\App\Support\CoffeeCardSample::available())
<div class="rounded-xl border border-stone-200 bg-white p-4">
    <label for="card_design" class="block text-base font-semibold text-stone-900">Card style</label>
    <select id="card_design" name="card_design" x-model="cardDesign" class="mt-2 block w-full rounded-lg border-stone-300 text-base" aria-describedby="card-design-help">
        <option value="classic" @selected(old('card_design', $value) === 'classic')>Classic — your colours and banner</option>
        <option value="illustrated_coffee" @selected(old('card_design', $value) === 'illustrated_coffee')>Illustrated coffee — coffee-cup stamps</option>
    </select>
    <p id="card-design-help" class="mt-2 text-sm text-stone-600">Coffee uses forest green, warm cream and mountain artwork with your logo. Up to 20 stamps appear as cups; larger targets use a progress bar. Your classic colours and banner are kept for switching back.</p>
    <x-input-error :messages="$errors->get('card_design')" class="mt-2" />
</div>
@endif
