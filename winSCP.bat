@echo off
"C:\Program Files (x86)\WinSCP\WinSCP.com" /log="C:\logs\winscp.log" /command ^
    "open sftp://uzivatel@tvujserver.cz/ -hostkey=""ssh-ed25519 ..."" -privatekey=""C:\cesta\k\tvemu\klici.ppk""" ^
    "get /cesta/ke/skriptu/exporty/*.xml C:\ucto\pohoda\import\" ^
    "exit"