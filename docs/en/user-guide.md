---
title: "PeopleDisplay — User Guide"
subtitle: "Version 2.3.0"
lang: en-GB
---

# Introduction

PeopleDisplay is a digital check-in board for staff. At a glance you see who is in, at which location, and who is a first-aid/emergency responder (in Dutch: *BHV'er*). Staff check in and out with a single tap on their own card. PeopleDisplay runs in the browser: on a large screen in the hall, on a tablet at the entrance, on a PC or on a phone. You can also install it as an app.

This guide is for everyone who uses PeopleDisplay day to day. Setting up and managing PeopleDisplay is covered in the **Administrator Guide**; installing it on your own hosting in the **Installation Guide**.

> **About the screen labels** — The PeopleDisplay interface is in Dutch. This guide shows the Dutch label in **bold**, followed by its meaning where that helps, for example **UIT** (OUT).

> **About the examples** — The screenshots were taken in a demo environment with fictional names and locations. Which parts you see depends on what your administrator has enabled for your account.

# Logging in

Open the address of your PeopleDisplay environment in your browser, for example `https://mijn.peopledisplay.nl`. You will see the login screen.

![The login screen](../images/login.png)

1. Enter your **gebruikersnaam** (username) and **wachtwoord** (password).
2. Tick **Blijf ingelogd** (stay logged in) to stay logged in on this device for 30 days. Only do this on a device nobody else uses.
3. Click **Inloggen** (log in).
4. If you have two-step verification on, PeopleDisplay now asks for the code from your authenticator app (see the chapter *Two-step verification*).

After logging in you arrive at the welcome screen. Administrators go straight to the admin dashboard.

> **Forgot your password?** Ask your administrator to set a new one. You will then choose a new password yourself at your next login.
>
> **Too many failed attempts?** After 5 wrong passwords you cannot log in for a while (15 minutes at first). Wait a moment and try again. A few wrong codes from the app count too.

# The welcome screen

On the welcome screen you choose where to go.

![The welcome screen](../images/frontpage.png)

| Tile | What it is for |
|---|---|
| **Aanmeldscherm** (check-in screen) | Checking staff in and out — the main screen |
| **Overzicht** (overview) | A list of everyone who is in, per location |
| **Mijn Profiel** (my profile) | Changing your name, email address, photo and password |
| **Infoscherm-berichten** (infoscreen messages) | Only when the administrator switched it on for you: posting messages on the infoscreen for your locations |
| **Beheer** (management) | Administrators only: the admin dashboard |

At the bottom is the **Uitloggen** (log out) button, with the PeopleDisplay version number below it.

# The check-in screen

The check-in screen is the heart of PeopleDisplay. Every staff member has a card. A **green** card means in (**IN**), a **red** card means out (**UIT**). Cards with an extra status (such as break or holiday) take the colour of that status.

![The check-in screen](../images/aanmeldscherm.png)

The screen refreshes itself every 30 seconds, so colleagues' changes appear automatically. At the bottom you can see when the screen was last refreshed.

## Your own card with a linked account

Has your administrator linked your account to your own staff card? Then instead of the full check-in screen you see only your own card, with your status shown above it: **📍 Ingecheckt op locatie "…"** (checked in at location "…") or **⚪ (Nog) niet ingecheckt** (not checked in (yet)). The search bar, filters and location menu are then not shown. If you are allowed to choose another location yourself, a button appears below your card: **🔄 Klik om een andere locatie te kiezen** (click to choose another location).

## The top bar

![The top bar with counters and buttons](../images/header.png)

- **Counters** — how many staff are in (✓) and out (✗), how many emergency responders are present (🚨) and how many staff have an extra status (for example ☕ break, 🏖️ holiday, 🏠 working from home).
- **Sort button (A)** — sort the cards by first name or by last name. Your choice is remembered on this device. The button only appears if your administrator enabled it for you.
- **− and +** — make the cards smaller or larger (zoom). Useful on a big screen or a small tablet. Also remembered per device.
- **Overzicht** — opens the overview page.
- **Fullscreen** — shows the check-in screen full screen. Press **Esc** to go back.
- **Your initial** (top left) — opens a menu with **Menu** (welcome screen), **Mijn Profiel** and **Uitloggen**. The version number is at the bottom of the menu; useful when your administrator or helpdesk asks which version you have.

## Choosing locations

Working with several locations? Click **TOON MENU** (show menu). A row of buttons appears with every location you are allowed to see.

![The location menu](../images/menu-locaties.png)

- Click a location to show only that location. Click more locations to combine them.
- **ALL** selects all locations, **CLEAR** clears your selection.
- Click **VERBERG MENU** (hide menu) to fold the row away.

## Searching and filtering

![The search and filter bar](../images/filters.png)

- **Zoek op naam** (search by name) — type part of a name; only matching cards remain.
- **Status** — for example show only who is in or out.
- **BHV** — show only emergency responders, or everyone except them.
- **Locatie** (location) and **Afdeling** (department) — limit the screen to one location or department.

As soon as a filter is active, a **Reset Filters** button appears with the number of active filters. On a shared screen the search and filters are cleared automatically after **1 minute without touching the screen**, so the next colleague sees the full screen again.

# Staff cards

![A staff card](../images/kaart.png)

A card can show the following (your administrator decides which are visible):

- **Photo** and **name**
- A **BHV** label if the person is an emergency responder
- **Job title**, **department** and **location**
- The **IN** and **UIT** (out) buttons
- Up to three **extra buttons**, for example PAUZE (break), THUISWERKEN (working from home) and VAKANTIE (holiday)
- A **📍 button** ("Pion") to check in at another location

## Checking in and out

Tap **IN** when you arrive and **UIT** when you leave. The card changes colour immediately and the counters at the top are updated.

## Extra statuses

The extra buttons tell colleagues that you are in but not available right now, or not on site but working. Your administrator decides which buttons exist and what they are called.

For some buttons PeopleDisplay asks **until when** the status applies:

![Until when does the status apply?](../images/tot-wanneer.png)

- Choose **Vandaag 17:00** (today 17:00), **Morgen 23:59** (tomorrow 23:59), **Over 1 week** (in one week), or **Aangepast…** (custom) to pick a date and time yourself.
- Click **Bevestigen** (confirm).

The card then shows the status and the end date. At that moment PeopleDisplay resets the status automatically.

![A card with an extra status and end date](../images/kaart-substatus.png)

Tap **IN** or **UIT** to end an extra status early.

## Checking in at another location ("Pion")

Working at a different location today? If your administrator enabled this for you, there is a **📍** button below your photo.

![Checking in at another location](../images/pion.png)

1. Tap **📍**.
2. Choose the location where you are today.
3. Tap **Inchecken op deze locatie** (check in at this location).

Your card now appears at that location. When you check out you automatically return to your regular location.

# Visitors

If visitor registration is enabled, two blocks appear above the cards:

![Expected visitors and visitors inside](../images/bezoekers-blok.png)

- **Verwachte Bezoekers** (expected visitors) — who registered for today but has not arrived yet. Tap **Check In** when the visitor arrives.
- **Bezoekers Binnen** (visitors inside) — who is in the building now. Tap **Check Out** when the visitor leaves.

## Registering a visitor

Visitors register themselves with the registration form (your administrator can put the link or a QR code at the entrance).

![The visitor registration form](../images/bezoeker-registratie.png)

1. Enter first name, last name and email address, and optionally phone number and company.
2. Choose the **location** and then the **contact person**.
3. Choose the visit date and expected time, or tick **multi-day visit**.
4. Click **Registreren** (register).

The contact person receives an email that the visitor has registered.

# Emergency overview (BHV)

Click **🚨 BHV Overzicht** at the bottom of the check-in screen. You see everyone who is currently **IN**, with emergency responders marked. This overview is meant for evacuations and emergencies.

![The emergency overview](../images/bhv-overzicht.png)

- **Print** or **PDF** — make a paper or digital list.
- **CSV** — download the list for Excel.
- **Versturen per e-mail** (send by email) — send the overview to a chosen or typed email address.
- The filters at the top limit the list to a department, location or emergency responders only.
- **Venster sluiten** (close window, red bar) takes you back — this also works in kiosk mode, where no browser buttons are visible.

# Overview

The overview page lists everyone who is in, grouped by location.

![The overview page](../images/overzicht.png)

At the top are the total, the number in and the number of emergency responders. You can search and filter as on the check-in screen. With **Auto-refresh** (at the bottom) the page refreshes every 30 seconds; **Ververs** (refresh) does so immediately. **Terug** (back) returns you to the check-in screen.

# IN/OUT board

The IN/OUT board is a simple two-column view: on the left who is **UIT** (out), on the right who is **IN**. It shows only names and a BHV label — ideal for a large screen in the hall.

![The IN/OUT board](../images/inuitbord.png)

- Click a name to check that person in or out.
- Choose a location at the top, or **Alle** (all).
- The buttons at the top right sort by first or last name, open the filters, show only emergency responders, go to the overview, log out, or put the board in full screen.
- The board refreshes itself every 30 seconds (**Live**).

If your administrator enabled the IN/OUT board for your account, the check-in screen always opens as the IN/OUT board for you.

# Infoscreen messages

If the administrator made you an **editor** (for example as reception or caretaker), the welcome screen shows the button **📝 Infoscherm-berichten** (infoscreen messages). With it you put messages on the infoscreen, for **your locations** only. Which ones those are is shown at the top, in the green box.

![Posting infoscreen messages for your own locations](../images/infoscherm-redacteur.png)

1. Click **Nieuw bericht** (new message).
2. Fill in a **Titel** (title) and a short **Tekst** (text), and optionally choose an image and a background colour. Below the text you see how many characters you use; keep to the advice and the text stays large and easy to read.
3. Under **Op welke locaties?** (which locations?) tick where the message must appear. You only see your own locations.
4. Optionally choose **Tonen vanaf** and **Tonen tot en met** (show from / until). Empty means: now, and it stays until you switch it off.
5. Tick **Belangrijk** (important) if the message must appear more often and also in the news ticker. Click **Opslaan** (save).

You can **edit** (Bewerken), **switch off** (Uitzetten) and **delete** (Verwijderen) your own messages. Messages from the administrator for **🌐 Alle locaties** (all locations), and messages that are also on other locations, show **🔒 Alleen lezen** (read only): the administrator changes those. **Voorbeeld** (preview) shows what the screen looks like.

# My profile

Use **Mijn Profiel** (welcome screen, or the menu under your initial) to change your own details.

![The profile page](../images/profiel.png)

- **Profielgegevens** (profile details) — display name and email address. Your role and username are shown for information.
- **Profielfoto** (profile photo) — upload a JPG, PNG or GIF file (max. 5 MB) or paste a link to a photo.
- **Wachtwoord wijzigen** (change password) — enter your current password and the new one twice (at least 10 characters). Preferably choose a phrase of three or four words.
- **Tweestapsverificatie** (two-step verification) — switch on or manage the extra protection with an app on your phone (see the next chapter).

If your administrator allows it, you can also choose your own names for the extra buttons here.

# Two-step verification (extra security)

A password alone is vulnerable: someone can spy on it, guess it or get it through a phishing email. With **two-step verification** (2FA) you therefore need, besides your password, a 6-digit code from an app on your phone to log in. Without your phone nobody can log in with just your password. If your administrator has made 2FA required, you will be asked to set it up when you log in. You then have 7 days (use **Later instellen**, set up later, to postpone). We recommend it even if it is not required.

## Setting up 2FA

You need your phone and about two minutes.

1. Install an authenticator app on your phone, for example **Google Authenticator**, **Microsoft Authenticator**, **Authy** or **1Password**. If you already have one, you can use it.
2. In PeopleDisplay go to **Mijn Profiel** and click **Instellen** (set up) under **Tweestapsverificatie**.
3. In the app choose **Add account** and scan the QR code on the screen. If scanning does not work, choose *enter manually* and type the key shown below the QR code. An account with the name shown on the screen appears in the app.
4. Enter the 6-digit code from the app and click **Bevestigen en aanzetten** (confirm and switch on).
5. You now see **10 recovery codes**. Keep them somewhere safe, for example in your password manager or printed (buttons **Printen**, **Kopiëren** and **Downloaden**). They are not shown again. Tick that you have saved them and click **Verder naar PeopleDisplay**.

> **Note:** make sure your phone's time is set automatically. If the clock is off, the codes will not work.

### Setting up on the same phone (no computer at hand)

If you only use PeopleDisplay on your phone or tablet, you cannot scan the QR code: you cannot scan the screen you are looking at. That is not needed. On a phone PeopleDisplay shows two buttons in step 3 instead of the QR code:

- **Open in je authenticator-app** (open in your authenticator app). Tap it: your authenticator app opens and adds the account by itself. Check that the account is in the app and go back to PeopleDisplay.
- **Sleutel kopiëren** (copy key). If the first button does not work (this differs per phone and app, especially on an iPhone), tap this one, open the app, choose *Enter a setup key* (or *enter manually*), paste the key and choose **time-based**. Give the account a recognisable name, for example PeopleDisplay.

If you do want to see the QR code, tap **Toch de QR-code tonen** (show the QR code anyway). Then continue with step 4: enter the 6-digit code from the app. You can also do it on a computer: open PeopleDisplay there, show the QR code and scan it with your phone.

## Logging in with 2FA

1. Enter your username and password as usual.
2. A screen **Tweede stap** (second step) appears. Open the app, look at the PeopleDisplay code and enter it. The code changes every 30 seconds.
3. Click **Inloggen**.

If you tick **Dit apparaat 30 dagen vertrouwen** (trust this device for 30 days), you do not have to enter a code on that device for 30 days. Only do this on your own computer or phone. **Blijf ingelogd** is something else and does not skip the code: after logging out PeopleDisplay asks for a code again, unless the device is trusted.

## Lost phone or no access?

Enter one of your **recovery codes** (for example `ABCDE-FGHIJ`) instead of the code from the app. Each recovery code works once. When only three or fewer are left, PeopleDisplay suggests making new ones. If you no longer have your phone or your recovery codes, ask your administrator to reset your 2FA. After that you can log in with your password again and set up 2FA anew.

## Managing 2FA

Under **Mijn Profiel** → **Tweestapsverificatie** → **Beheren** (manage) you can:

- **make new recovery codes** (your password is required; the old codes stop working);
- **sign out all trusted devices**, for example if you lost a device;
- **switch 2FA off** (your password is required). This is not possible if your administrator has made 2FA required for your account.

Got a new phone? Switch 2FA off and on again first, or ask your administrator for a reset, and scan the new QR code with your new phone. A new password signs out all trusted devices.

# PeopleDisplay on a phone or tablet

PeopleDisplay adapts to the screen. On a phone the counters, buttons and filters are stacked.

![PeopleDisplay on a phone](../images/mobiel.png)

## Installing as an app

You can install PeopleDisplay as an app, so it gets its own icon and opens without an address bar.

- **Android (Chrome)** — tap the three dots and choose **Install app** or **Add to Home screen**.
- **iPhone/iPad (Safari)** — tap the share icon (square with an arrow) and choose **Add to Home Screen**.
- **Windows/Mac (Chrome or Edge)** — click the install icon at the right of the address bar.

After a PeopleDisplay update the app refreshes itself within a few minutes.

## Automatic check-in on Wi-Fi

If your administrator set this up for a location, the installed app offers to check you in automatically as soon as your phone is connected to that location's network. You can always simply tap IN or OUT yourself as well.

# Frequently asked questions

**I can't find my name on the screen.**
Check whether a location or department filter is active (**Reset Filters** button). If your card is still missing, ask your administrator whether you have been added as a staff member, and at which location.

**I don't see the extra buttons, the sort button or the IN/OUT board.**
Your administrator enables these features per user.

**The screen shows old information.**
The screen refreshes every 30 seconds. Reload the page (F5, or swipe down on a tablet) to see the latest state immediately.

**I was logged out automatically.**
An administrator can end sessions remotely, for example when a device is lost. Just log in again.

**The code from the app does not work.**
Check that your phone's time is set automatically. In Google Authenticator that is *Settings → Time correction for codes → Sync now*. If your app holds two accounts with almost the same name (from an earlier attempt to set up), use the right one and delete the old one.

**I lost my phone.**
Log in with a recovery code. If you no longer have those, ask your administrator to reset your 2FA.

**My extra status is still shown although I'm back.**
Tap **IN**; the extra status ends immediately.
