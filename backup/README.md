# backup/

This folder exists only on the `backup` branch. `main` does not carry it.

- **b1/** — full snapshot of the *first backup branch* (the pre-bugfix restore
  point of this repository, i.e. the state before the loader/instance bugfixes
  were applied).
- **raw/** — raw, unmodified content of the external repositories that were
  integrated into this hub, kept so the source repos can be deleted safely:
  - `raw/netplan/`
  - `raw/kiosk-dashbourd/`
  - `raw/nexus/`
  - `raw/dark-money/`

`wificracker` is intentionally **not** included: it is automated Wi-Fi
attack tooling and is not republished here.
