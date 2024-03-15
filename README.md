### php-reverse-shell

Yet another php reverse shell for pentesters

### Features

- Compatible with [netcat](https://en.wikipedia.org/wiki/Netcat) and [ncat](https://nmap.org/ncat)
- Host resolution via [DoH](https://en.wikipedia.org/wiki/DNS_over_HTTPS)
  and [DDNS](https://en.wikipedia.org/wiki/Dynamic_DNS)
- Support for [daemonization](https://en.wikipedia.org/wiki/Daemon_(computing))
  and [encryption](https://en.wikipedia.org/wiki/Transport_Layer_Security)
- Semi-automatic [shell stabilization](https://0xffsec.com/handbook/shells/full-tty/)

### Usage

- Register domain on DDNS service https://freemyip.com
- Save link to update IPv4 address (`https://freemyip.com/update?token=my-token&domain=my-domain.freemyip.com`)
- Use link to update IPv4 address
- Change config in [`shell.php`](shell.php) file
- Upload backdoor to vulnerable server
- Create new terminal window or tab
    - Open port from config
    - Start listening on port from config:
        - `ncat -nvlp 1337 --ssl` (with encryption, recommend)
        - `nc -nlvp 1337` (without encryption)
    - Visit `/start.php` on vulnerable server
    - Wait for shell to appear in terminal
    - Press `Ctrl + Z` in terminal
    - Run `stty raw -echo; fg` in terminal and press `Enter`

_NOTE_: to exit from shell use `Ctrl + D` twice
