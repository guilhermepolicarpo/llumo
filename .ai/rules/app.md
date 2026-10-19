---
paths:
  - 'app/**'
---

# App

## No PHP Events/Listeners layer
This app has no `app/Events` or `app/Listeners` directories. Actions and models call each other directly rather than dispatching custom PHP events. Cross-component communication instead uses Livewire's own `$this->dispatch('name')` paired with `#[On('name')]` listener methods. Don't introduce an events/listeners layer for decoupling here — match the existing direct-call style.

## Dates: CarbonImmutable + now() helper
`AppServiceProvider` calls `Date::use(CarbonImmutable::class)`, so all date helpers return immutable instances app-wide. Use the `now()`/`today()` helpers for construction rather than `Carbon::now()`/`Carbon::` static calls.

## Appointment timestamps record data entry, not the session
Centers run sessions on paper: the mentor fills the form in the room and the front desk enters it afterwards. On `Appointment`, only `received_at` is real (the front desk clicks "Receber" on arrival). `started_at` and `finished_at` are when the session was entered into the system, so never show their difference as session length or waiting time; show them as discreet entry records. `attendant_id` is the front-desk user who clicked "Atender"; whoever actually attended is `AppointmentRecord::mentor` (`mentor_id`), so prefer the mentor in any screen. "Aguardando" may show while the person is already in the room. This applies to views and PDFs that display appointments too.
