{strip}
    {if !$width}{assign var="width" value="40"}{/if}
    {if !$height}{assign var="height" value="40"}{/if}
    {if !$fill}{assign var="fill" value="#000000"}{/if}

    {if $svgId == "export"}
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
            stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M14 3v4a1 1 0 0 0 1 1h4" />
            <path d="M11.5 21h-4.5a2 2 0 0 1 -2 -2v-14a2 2 0 0 1 2 -2h7l5 5v5m-5 6h7m-3 -3l3 3l-3 3" />
        </svg>
    {/if}
    {if $svgId == "import"}
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none"
            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M14 3v4a1 1 0 0 0 1 1h4" />
            <path d="M5 13v-8a2 2 0 0 1 2 -2h7l5 5v11a2 2 0 0 1 -2 2h-5.5m-9.5 -2h7m-3 -3l3 3l-3 3" />
        </svg>
    {/if}
    {if $svgId == "print"}
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
            stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M17 17h2a2 2 0 0 0 2 -2v-4a2 2 0 0 0 -2 -2h-14a2 2 0 0 0 -2 2v4a2 2 0 0 0 2 2h2" />
            <path d="M17 9v-4a2 2 0 0 0 -2 -2h-6a2 2 0 0 0 -2 2v4" />
            <path d="M7 15a2 2 0 0 1 2 -2h6a2 2 0 0 1 2 2v4a2 2 0 0 1 -2 2h-6a2 2 0 0 1 -2 -2l0 -4" />
        </svg>
    {/if}
    {if $svgId == "eye"}
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
            stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M10 12a2 2 0 1 0 4 0a2 2 0 0 0 -4 0" />
            <path d="M21 12c-2.4 4 -5.4 6 -9 6c-3.6 0 -6.6 -2 -9 -6c2.4 -4 5.4 -6 9 -6c3.6 0 6.6 2 9 6" />
        </svg>
    {/if}
    {if $svgId == "prev"}
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
            stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
            <path d="M15 6l-6 6l6 6" />
        </svg>
    {/if}
    {if $svgId == "next"}
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
            stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
            <path d="M9 6l6 6l-6 6" />
        </svg>
    {/if}
    {if $svgId == "left_catalog"}
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
            stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M4 6a2 2 0 0 1 2 -2h12a2 2 0 0 1 2 2v2a2 2 0 0 1 -2 2h-12a2 2 0 0 1 -2 -2l0 -2" />
            <path d="M4 16a2 2 0 0 1 2 -2h12a2 2 0 0 1 2 2v2a2 2 0 0 1 -2 2h-12a2 2 0 0 1 -2 -2l0 -2" />
        </svg>
    {/if}
    {if $svgId == "date"}
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
            stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M4 7a2 2 0 0 1 2 -2h12a2 2 0 0 1 2 2v12a2 2 0 0 1 -2 2h-12a2 2 0 0 1 -2 -2v-12" />
            <path d="M16 3v4" />
            <path d="M8 3v4" />
            <path d="M4 11h16" />
            <path d="M8 14v4" />
            <path d="M12 14v4" />
            <path d="M16 14v4" />
        </svg>
    {/if}
    {if $svgId == "return"}
        <svg xmlns="http://www.w3.org/2000/svg" width="20px" height="20px" viewBox="0 0 24 24" fill="none"
            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4">
            <path d="M9 14l-4 -4l4 -4" />
            <path d="M5 10h11a4 4 0 1 1 0 8h-1" />
        </svg>
    {/if}
    {if $svgId == "winner"}
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
            stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M8 21l8 0" />
            <path d="M12 17l0 4" />
            <path d="M7 4l10 0" />
            <path d="M17 4v8a5 5 0 0 1 -10 0v-8" />
            <path d="M3 9a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" />
            <path d="M17 9a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" />
        </svg>
    {/if}
    {if $svgId == "add"}
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
            stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M3 12a9 9 0 1 0 18 0a9 9 0 0 0 -18 0" />
            <path d="M9 12h6" />
            <path d="M12 9v6" />
        </svg>
    {/if}
    {if $svgId == "mobile_menu"}
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
            stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M4 6l16 0" />
            <path d="M4 12l16 0" />
            <path d="M4 18l16 0" />
        </svg>
    {/if}
    {if $svgId == "mobile_menu2"}
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
            stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M4 6h16" />
            <path d="M7 12h13" />
            <path d="M10 18h10" />
        </svg>
    {/if}
    {if $svgId == "order_list"}
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
            stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M9 5h-2a2 2 0 0 0 -2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2 -2v-12a2 2 0 0 0 -2 -2h-2" />
            <path d="M9 5a2 2 0 0 1 2 -2h2a2 2 0 0 1 2 2a2 2 0 0 1 -2 2h-2a2 2 0 0 1 -2 -2" />
            <path d="M9 12l.01 0" />
            <path d="M13 12l2 0" />
            <path d="M9 16l.01 0" />
            <path d="M13 16l2 0" />
        </svg>
    {/if}
    {if $svgId == "check"}
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
            stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
            class="check_svg">
            <path d="M3 12a9 9 0 1 0 18 0a9 9 0 1 0 -18 0" />
            <path d="M9 12l2 2l4 -4" />
        </svg>
    {/if}
    {if $svgId == "exchange"}
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
            stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M7 10h14l-4 -4" />
            <path d="M17 14h-14l4 4" />
        </svg>
    {/if}
    {if $svgId == "yes_icon"}
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
            stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
            <path d="M3 12a9 9 0 1 0 18 0a9 9 0 1 0 -18 0" />
            <path d="M9 12l2 2l4 -4" />
        </svg>
    {/if}
    {if $svgId == "no_icon"}
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
            stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
            <path d="M3 12a9 9 0 1 0 18 0a9 9 0 1 0 -18 0" />
            <path d="M10 10l4 4m0 -4l-4 4" />
        </svg>
    {/if}
    {if $svgId == "checked"}
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
            stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M5 12l5 5l10 -10" />
        </svg>
    {/if}
    {if $svgId == "email"}
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
            stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M3 7a2 2 0 0 1 2 -2h14a2 2 0 0 1 2 2v10a2 2 0 0 1 -2 2h-14a2 2 0 0 1 -2 -2v-10" />
            <path d="M3 7l9 6l9 -6" />
        </svg>
    {/if}
    {if $svgId == "phone"}
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
            stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M5 4h4l2 5l-2.5 1.5a11 11 0 0 0 5 5l1.5 -2.5l5 2v4a2 2 0 0 1 -2 2a16 16 0 0 1 -15 -15a2 2 0 0 1 2 -2" />
        </svg>
    {/if}
    {if $svgId == "tag"}
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
            stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M6.5 7.5a1 1 0 1 0 2 0a1 1 0 1 0 -2 0" />
            <path d="M3 6v5.172a2 2 0 0 0 .586 1.414l7.71 7.71a2.41 2.41 0 0 0 3.408 0l5.592 -5.592a2.41 2.41 0 0 0 0 -3.408l-7.71 -7.71a2 2 0 0 0 -1.414 -.586h-5.172a3 3 0 0 0 -3 3" />
        </svg>
    {/if}
    {if $svgId == "magic"}
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
            stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
            <path d="M6 21l15 -15l-3 -3l-15 15l3 3" />
            <path d="M15 6l3 3" />
            <path d="M9 3a2 2 0 0 0 2 2a2 2 0 0 0 -2 2a2 2 0 0 0 -2 -2a2 2 0 0 0 2 -2" />
            <path d="M19 13a2 2 0 0 0 2 2a2 2 0 0 0 -2 2a2 2 0 0 0 -2 -2a2 2 0 0 0 2 -2" />
        </svg>
    {/if}
    {if $svgId == "infinity"}
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
            stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M9.828 9.172a4 4 0 1 0 0 5.656a10 10 0 0 0 2.172 -2.828a10 10 0 0 1 2.172 -2.828a4 4 0 1 1 0 5.656a10 10 0 0 1 -2.172 -2.828a10 10 0 0 0 -2.172 -2.828" />
        </svg>
    {/if}
    {if $svgId == "sorts"}
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
            stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path class="left_s" d="M3 15l4 4l4 -4m-4 4v-14" />
            <path class="right_s" d="M21 9l-4 -4l-4 4m4 -4v14" />
        </svg>
    {/if}
    {if $svgId == "sorts2"}
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
            stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path class="left_s" d="M21 15l-4 4l-4 -4m4 4v-14" />
            <path class="right_s" d="M3 9l4 -4l4 4m-4 -4v14" />
        </svg>
    {/if}
    {if $svgId == "sorting"}
        <svg viewBox="0 0 401.998 401.998" width="20px" height="20px">
            <path class="left_s" d="M73.092,164.452h255.813c4.949,0,9.233-1.807,12.848-5.424c3.613-3.616,5.427-7.898,5.427-12.847
            c0-4.949-1.813-9.229-5.427-12.85L213.846,5.424C210.232,1.812,205.951,0,200.999,0s-9.233,1.812-12.85,5.424L60.242,133.331
            c-3.617,3.617-5.424,7.901-5.424,12.85c0,4.948,1.807,9.231,5.424,12.847C63.863,162.645,68.144,164.452,73.092,164.452z"/>
            <path class="right_s" d="M328.905,237.549H73.092c-4.952,0-9.233,1.808-12.85,5.421c-3.617,3.617-5.424,7.898-5.424,12.847
            c0,4.949,1.807,9.233,5.424,12.848L188.149,396.57c3.621,3.617,7.902,5.428,12.85,5.428s9.233-1.811,12.847-5.428l127.907-127.906
            c3.613-3.614,5.427-7.898,5.427-12.848c0-4.948-1.813-9.229-5.427-12.847C338.139,239.353,333.854,237.549,328.905,237.549z"/>
        </svg>
    {/if}
    {if $svgId == "plus"}
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
            stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
            <path d="M12 5l0 14" />
            <path d="M5 12l14 0" />
        </svg>
    {/if}
    {if $svgId == "minus"}
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
            stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
            <path d="M5 12l14 0" />
        </svg>
    {/if}
    {if $svgId == "drag_vertical"}
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
            stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M8 5a1 1 0 1 0 2 0a1 1 0 1 0 -2 0" />
            <path d="M8 12a1 1 0 1 0 2 0a1 1 0 1 0 -2 0" />
            <path d="M8 19a1 1 0 1 0 2 0a1 1 0 1 0 -2 0" />
            <path d="M14 5a1 1 0 1 0 2 0a1 1 0 1 0 -2 0" />
            <path d="M14 12a1 1 0 1 0 2 0a1 1 0 1 0 -2 0" />
            <path d="M14 19a1 1 0 1 0 2 0a1 1 0 1 0 -2 0" />
        </svg>
    {/if}

    {if $svgId == "notify"}
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
            stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
            <path d="M10 5a2 2 0 1 1 4 0a7 7 0 0 1 4 6v3a4 4 0 0 0 2 3h-16a4 4 0 0 0 2 -3v-3a7 7 0 0 1 4 -6" />
            <path d="M9 17v1a3 3 0 0 0 6 0v-1" />
        </svg>
    {/if}
    {if $svgId == "exit"}
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
            stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
            <path d="M14 8v-2a2 2 0 0 0 -2 -2h-7a2 2 0 0 0 -2 2v12a2 2 0 0 0 2 2h7a2 2 0 0 0 2 -2v-2" />
            <path d="M9 12h12l-3 -3" />
            <path d="M18 15l3 -3" />
        </svg>
    {/if}
    {if $svgId == "help_icon"}
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
            stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
            <path d="M3 12a9 9 0 1 0 18 0a9 9 0 0 0 -18 0" />
            <path d="M12 16v.01" />
            <path d="M12 13a2 2 0 0 0 .914 -3.782a1.98 1.98 0 0 0 -2.414 .483" />
        </svg>
    {/if}

    {if $svgId == "warn_icon"}
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
            stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
            <path d="M12 9v4" />
            <path d="M10.363 3.591l-8.106 13.534a1.914 1.914 0 0 0 1.636 2.871h16.214a1.914 1.914 0 0 0 1.636 -2.87l-8.106 -13.536a1.914 1.914 0 0 0 -3.274 0" />
            <path d="M12 16h.01" />
        </svg>
    {/if}

    {if $svgId == "info_icon"}
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
            stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
            <path d="M3 12a9 9 0 1 0 18 0a9 9 0 0 0 -18 0" />
            <path d="M12 9h.01" />
            <path d="M11 12h1v4h1" />
        </svg>
    {/if}
    {if $svgId == "video_icon"}
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
            stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
            <path d="M7 4v16l13 -8l-13 -8" />
        </svg>
    {/if}
    {if $svgId == "delete"}
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
            stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
            <path d="M18 6l-12 12" />
            <path d="M6 6l12 12" />
        </svg>
    {/if}
    {if $svgId == "trash"}
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
            stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M4 7l16 0" />
            <path d="M10 11l0 6" />
            <path d="M14 11l0 6" />
            <path d="M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2 -2l1 -12" />
            <path d="M9 7v-3a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v3" />
        </svg>
    {/if}
    {if $svgId == "icon_featured"}
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
            stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M12 17.75l-6.172 3.245l1.179 -6.873l-5 -4.867l6.9 -1l3.086 -6.253l3.086 6.253l6.9 1l-5 4.867l1.179 6.873l-6.158 -3.245" />
        </svg>
    {/if}

    {if $svgId == "icon_copy"}
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
            stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M7 9.667a2.667 2.667 0 0 1 2.667 -2.667h8.666a2.667 2.667 0 0 1 2.667 2.667v8.666a2.667 2.667 0 0 1 -2.667 2.667h-8.666a2.667 2.667 0 0 1 -2.667 -2.667l0 -8.666" />
            <path d="M4.012 16.737a2.005 2.005 0 0 1 -1.012 -1.737v-10c0 -1.1 .9 -2 2 -2h10c.75 0 1.158 .385 1.5 1" />
        </svg>
    {/if}
    {if $svgId == "icon_desktop"}
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
            stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
            <path d="M12 6h-6a2 2 0 0 0 -2 2v10a2 2 0 0 0 2 2h10a2 2 0 0 0 2 -2v-6" />
            <path d="M11 13l9 -9" />
            <path d="M15 4h5v5" />
        </svg>
    {/if}
    {if $svgId == "icon_tooltips"}
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
            stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
            <path d="M3 12a9 9 0 1 0 18 0a9 9 0 0 0 -18 0" />
            <path d="M12 16v.01" />
            <path d="M12 13a2 2 0 0 0 .914 -3.782a1.98 1.98 0 0 0 -2.414 .483" />
        </svg>
    {/if}
    {if $svgId == "techsupport"}
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
            stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
            <path d="M4 14v-3a8 8 0 1 1 16 0v3" />
            <path d="M18 19c0 1.657 -2.686 3 -6 3" />
            <path d="M4 14a2 2 0 0 1 2 -2h1a2 2 0 0 1 2 2v3a2 2 0 0 1 -2 2h-1a2 2 0 0 1 -2 -2v-3" />
            <path d="M15 14a2 2 0 0 1 2 -2h1a2 2 0 0 1 2 2v3a2 2 0 0 1 -2 2h-1a2 2 0 0 1 -2 -2v-3" />
        </svg>
    {/if}
    {if $svgId == "logout"}
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
            stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M14 8v-2a2 2 0 0 0 -2 -2h-7a2 2 0 0 0 -2 2v12a2 2 0 0 0 2 2h7a2 2 0 0 0 2 -2v-2" />
            <path d="M9 12h12l-3 -3" />
            <path d="M18 15l3 -3" />
        </svg>
    {/if}
    {if $svgId == "left_orders"}
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
            stroke-width="2" stroke-linecap="round" stroke-linejoin="round" id="shopping-cart">
            <path d="M4 19a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" />
            <path d="M15 19a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" />
            <path d="M17 17h-11v-14h-2" />
            <path d="M6 5l14 1l-1 7h-13" />
        </svg>
    {/if}
    {if $svgId == "left_users"}
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
            stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M5 7a4 4 0 1 0 8 0a4 4 0 1 0 -8 0" />
            <path d="M3 21v-2a4 4 0 0 1 4 -4h4a4 4 0 0 1 4 4v2" />
            <path d="M16 3.13a4 4 0 0 1 0 7.75" />
            <path d="M21 21v-2a4 4 0 0 0 -3 -3.85" />
        </svg>
    {/if}
    {if $svgId == "left_modules"}
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
            stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M4 7h3a1 1 0 0 0 1 -1v-1a2 2 0 0 1 4 0v1a1 1 0 0 0 1 1h3a1 1 0 0 1 1 1v3a1 1 0 0 0 1 1h1a2 2 0 0 1 0 4h-1a1 1 0 0 0 -1 1v3a1 1 0 0 1 -1 1h-3a1 1 0 0 1 -1 -1v-1a2 2 0 0 0 -4 0v1a1 1 0 0 1 -1 1h-3a1 1 0 0 1 -1 -1v-3a1 1 0 0 1 1 -1h1a2 2 0 0 0 0 -4h-1a1 1 0 0 1 -1 -1v-3a1 1 0 0 1 1 -1" />
        </svg>
    {/if}
    {if $svgId == "left_faq_title"}
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
            stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M3 12a9 9 0 1 0 18 0a9 9 0 0 0 -18 0" />
            <path d="M12 16v.01" />
            <path d="M12 13a2 2 0 0 0 .914 -3.782a1.98 1.98 0 0 0 -2.414 .483" />
        </svg>
    {/if}
    {if $svgId == "left_pages"}
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
            stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M4 8h16" />
            <path d="M4 6a2 2 0 0 1 2 -2h12a2 2 0 0 1 2 2v12a2 2 0 0 1 -2 2h-12a2 2 0 0 1 -2 -2l0 -12" />
            <path d="M8 4v4" />
        </svg>
    {/if}
    {if $svgId == "left_blog"}
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
            stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M7 7h-1a2 2 0 0 0 -2 2v9a2 2 0 0 0 2 2h9a2 2 0 0 0 2 -2v-1" />
            <path d="M20.385 6.585a2.1 2.1 0 0 0 -2.97 -2.97l-8.415 8.385v3h3l8.385 -8.415" />
            <path d="M16 5l3 3" />
        </svg>
    {/if}
    {if $svgId == "sertificat"}
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
            stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M12 15a3 3 0 1 0 6 0a3 3 0 1 0 -6 0" />
            <path d="M13 17.5v4.5l2 -1.5l2 1.5v-4.5" />
            <path d="M10 19h-5a2 2 0 0 1 -2 -2v-10c0 -1.1 .9 -2 2 -2h14a2 2 0 0 1 2 2v10a2 2 0 0 1 -1 1.73" />
            <path d="M6 9l12 0" />
            <path d="M6 12l3 0" />
            <path d="M6 15l2 0" />
        </svg>
    {/if}
    {if $svgId == "left_comments"}
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
            stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M8 9h8" />
            <path d="M8 13h6" />
            <path d="M9 18h-3a3 3 0 0 1 -3 -3v-8a3 3 0 0 1 3 -3h12a3 3 0 0 1 3 3v8a3 3 0 0 1 -3 3h-3l-3 3l-3 -3" />
        </svg>
    {/if}
    {if $svgId == "left_auto"}
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
            stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M3 9l4 -4l4 4m-4 -4v14" />
            <path d="M21 15l-4 4l-4 -4m4 4v-14" />
        </svg>
    {/if}
    {if $svgId == "left_stats"}
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
            stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M3 13a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v6a1 1 0 0 1 -1 1h-4a1 1 0 0 1 -1 -1l0 -6" />
            <path d="M15 9a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v10a1 1 0 0 1 -1 1h-4a1 1 0 0 1 -1 -1l0 -10" />
            <path d="M9 5a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v14a1 1 0 0 1 -1 1h-4a1 1 0 0 1 -1 -1l0 -14" />
            <path d="M4 20h14" />
        </svg>
    {/if}
    {if $svgId == "left_seo"}
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
            stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M4 8v-2a2 2 0 0 1 2 -2h2" />
            <path d="M4 16v2a2 2 0 0 0 2 2h2" />
            <path d="M16 4h2a2 2 0 0 1 2 2v2" />
            <path d="M16 20h2a2 2 0 0 0 2 -2v-2" />
            <path d="M9 12l6 0" />
            <path d="M12 9l0 6" />
        </svg>
    {/if}
    {if $svgId == "left_topvisor_title"}
        <svg width="20" height="20" viewBox="0 0 21 21" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">
            <g fill="none"><path d="M10.453 9.471a1.89 1.89 0 0 0-2.39 1.82 1.89 1.89 0 0 0 1.888 1.887 1.89 1.89 0 0 0 1.819-2.39l5.258-5.259.851.114 2.662-2.662-2.013-.267L18.26.7l-2.662 2.662.114.85-5.259 5.26z" fill="currentColor"/><g transform="translate(.72 2.033)"><path d="M9.434 5.631l1.555-1.555a5.464 5.464 0 0 0-5.627 1.312 5.475 5.475 0 0 0 0 7.739 5.475 5.475 0 0 0 7.738 0 5.471 5.471 0 0 0 1.313-5.632l-1.556 1.556a3.634 3.634 0 1 1-6.195-2.362A3.615 3.615 0 0 1 9.434 5.63z" fill="currentColor" /><path d="M15.754 6.158a7.216 7.216 0 0 1-1.418 8.204 7.218 7.218 0 0 1-10.21 0 7.219 7.219 0 0 1 0-10.21 7.223 7.223 0 0 1 8.204-1.417l1.487-1.487C10.292-.774 5.714-.28 2.704 2.73-.9 6.336-.9 12.179 2.704 15.78c3.606 3.605 9.448 3.605 13.05 0 3.01-3.011 3.504-7.59 1.483-11.114l-1.483 1.491z" fill="currentColor" /></g></g></svg>
    {/if}
    {if $svgId == "left_design"}
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
            stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M3 21v-4a4 4 0 1 1 4 4h-4" />
            <path d="M21 3a16 16 0 0 0 -12.8 10.2" />
            <path d="M21 3a16 16 0 0 1 -10.2 12.8" />
            <path d="M10.6 9a9 9 0 0 1 4.4 4.4" />
        </svg>
    {/if}
    {if $svgId == "left_settings"}
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
            stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M10.325 4.317c.426 -1.756 2.924 -1.756 3.35 0a1.724 1.724 0 0 0 2.573 1.066c1.543 -.94 3.31 .826 2.37 2.37a1.724 1.724 0 0 0 1.065 2.572c1.756 .426 1.756 2.924 0 3.35a1.724 1.724 0 0 0 -1.066 2.573c.94 1.543 -.826 3.31 -2.37 2.37a1.724 1.724 0 0 0 -2.572 1.065c-.426 1.756 -2.924 1.756 -3.35 0a1.724 1.724 0 0 0 -2.573 -1.066c-1.543 .94 -3.31 -.826 -2.37 -2.37a1.724 1.724 0 0 0 -1.065 -2.572c-1.756 -.426 -1.756 -2.924 0 -3.35a1.724 1.724 0 0 0 1.066 -2.573c-.94 -1.543 .826 -3.31 2.37 -2.37c1 .608 2.296 .07 2.572 -1.065" />
            <path d="M9 12a3 3 0 1 0 6 0a3 3 0 0 0 -6 0" />
        </svg>
    {/if}
    {if $svgId == "refresh_icon"}
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
            stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M20 11a8.1 8.1 0 0 0 -15.5 -2m-.5 -4v4h4" />
            <path d="M4 13a8.1 8.1 0 0 0 15.5 2m.5 4v-4h-4" />
        </svg>
    {/if}
    {if $svgId == "user_icon"}
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
            stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
            <path d="M8 7a4 4 0 1 0 8 0a4 4 0 0 0 -8 0" />
            <path d="M6 21v-2a4 4 0 0 1 4 -4h4a4 4 0 0 1 4 4v2" />
        </svg>
    {/if}
    {if $svgId == "user2_icon"}
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
            stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
            <path d="M3 12a9 9 0 1 0 18 0a9 9 0 1 0 -18 0" />
            <path d="M9 10a3 3 0 1 0 6 0a3 3 0 1 0 -6 0" />
            <path d="M6.168 18.849a4 4 0 0 1 3.832 -2.849h4a4 4 0 0 1 3.834 2.855" />
        </svg>
    {/if}
    {if $svgId == "pass_icon"}
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
            stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
            <path d="M5 13a2 2 0 0 1 2 -2h10a2 2 0 0 1 2 2v6a2 2 0 0 1 -2 2h-10a2 2 0 0 1 -2 -2v-6" />
            <path d="M11 16a1 1 0 1 0 2 0a1 1 0 0 0 -2 0" />
            <path d="M8 11v-4a4 4 0 1 1 8 0v4" />
        </svg>
    {/if}

    {if $svgId == "tag_social"}
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
            stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M3 12a3 3 0 1 0 6 0a3 3 0 1 0 -6 0" />
            <path d="M15 6a3 3 0 1 0 6 0a3 3 0 1 0 -6 0" />
            <path d="M15 18a3 3 0 1 0 6 0a3 3 0 1 0 -6 0" />
            <path d="M8.7 10.7l6.6 -3.4" />
            <path d="M8.7 13.3l6.6 3.4" />
        </svg>
    {/if}
    {if $svgId == "tag_email"}
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
            stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M3 7a2 2 0 0 1 2 -2h14a2 2 0 0 1 2 2v10a2 2 0 0 1 -2 2h-14a2 2 0 0 1 -2 -2v-10" />
            <path d="M3 7l9 6l9 -6" />
        </svg>
    {/if}

    {if $svgId == "tag_search"}
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
            stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M3 10a7 7 0 1 0 14 0a7 7 0 1 0 -14 0" />
            <path d="M21 21l-6 -6" />
        </svg>
    {/if}

    {if $svgId == "tag_referral"}
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
            stroke-width="2.75" stroke-linecap="round" stroke-linejoin="round">
            <path d="M9 15l6 -6" />
            <path d="M11 6l.463 -.536a5 5 0 0 1 7.071 7.072l-.534 .464" />
            <path d="M13 18l-.397 .534a5.068 5.068 0 0 1 -7.127 0a4.972 4.972 0 0 1 0 -7.071l.524 -.463" />
        </svg>
    {/if}

    {if $svgId == "tag_unknown"}
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
            stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M9.442 9.432a3 3 0 0 0 4.113 4.134m1.445 -2.566a3 3 0 0 0 -3 -3" />
            <path d="M17.152 17.162l-3.738 3.738a2 2 0 0 1 -2.827 0l-4.244 -4.243a8 8 0 0 1 -.476 -10.794m2.18 -1.82a8.003 8.003 0 0 1 10.91 10.912" />
            <path d="M3 3l18 18" />
        </svg>
    {/if}
    {if $svgId == "modules_icon"}
    <svg viewBox="0 0 512 512" width="60px" height="60px">
        <path style="fill:#FEC9A3;" d="M507.661,503.322V399.907c0-11.325-5.528-21.938-14.805-28.438l-65.25-45.681
            c-4.374-3.063-9.589-4.703-14.935-4.703h-52.536l0.868,148.48c0.321,15.473,10.431,29.028,25.166,33.757H507.661z"/>
        <path style="fill:#FABD91;" d="M455.593,503.322h-60.746c-19.17,0-34.712-15.542-34.712-34.712v-34.712h60.746L455.593,503.322z"/>
        <path style="fill:#88B337;" d="M229.966,60.746c-5.979,0.009-11.889,1.258-17.356,3.662V0H56.407
            C27.648,0,4.339,23.309,4.339,52.068v156.203h64.408c-9.633-21.938,0.338-47.538,22.285-57.17
            c21.938-9.633,47.538,0.338,57.17,22.285c4.886,11.116,4.886,23.778,0,34.894h64.408v-64.408
            c21.938,9.633,47.538-0.338,57.17-22.285s-0.338-47.538-22.285-57.17C241.976,61.978,236.006,60.737,229.966,60.746z"/>
        <path style="fill:#E8594A;" d="M420.881,52.068C420.881,23.309,397.572,0,368.814,0H212.61v64.408
            c21.938-9.633,47.538,0.338,57.17,22.285c9.633,21.938-0.338,47.538-22.285,57.17c-11.116,4.886-23.778,4.886-34.894,0v64.408
            h64.408c-9.633,21.938,0.338,47.538,22.285,57.17c21.938,9.633,47.538-0.338,57.17-22.285c4.886-11.116,4.886-23.778,0-34.894
            h64.417V52.068z"/>
        <path style="fill:#D65245;" d="M420.881,57.023C371.278,126.03,281.73,165.228,212.61,186.325v21.947h64.408
            c-9.633,21.938,0.338,47.538,22.285,57.17c21.938,9.633,47.538-0.338,57.17-22.285c4.886-11.116,4.886-23.778,0-34.894h64.408
            V57.023z"/>
        <path style="fill:#FDB62F;" d="M195.254,277.695c5.979,0.009,11.889,1.258,17.356,3.662v-73.086h-64.408
            c9.633-21.938-0.338-47.538-22.285-57.17c-21.947-9.633-47.538,0.338-57.17,22.285c-4.886,11.116-4.886,23.778,0,34.894H4.339
            v156.203c0,28.759,23.309,52.068,52.068,52.068H212.61v-55.73c-21.938,9.633-47.538-0.338-57.17-22.285
            c-9.633-21.938,0.338-47.538,22.285-57.17C183.244,278.927,189.214,277.686,195.254,277.695z"/>
        <path style="fill:#FFA719;" d="M4.339,364.475c0,28.759,23.309,52.068,52.068,52.068H212.61v-55.73
            c-21.834,9.615-47.33-0.286-56.945-22.12c-2.23-5.068-3.471-10.526-3.645-16.063c-46.523,20.749-96.751,31.918-147.682,32.846
            V364.475z"/>
        <path style="fill:#4398D1;" d="M443.574,389.641l-54.906-146.658l-60.303,22.71c16.809,17.122,16.549,44.631-0.573,61.431
            c-17.122,16.801-44.631,16.549-61.431-0.573c-8.556-8.721-13.052-20.636-12.375-32.837l-60.295,22.71l25.687,68.634
            c-5.962-0.338-11.932,0.573-17.529,2.673c-22.407,8.695-33.514,33.905-24.819,56.311c8.548,22.016,33.098,33.202,55.322,25.183
            c5.597-2.109,10.7-5.372,14.969-9.563L266.9,512l146.241-55.079C440.094,446.716,453.71,416.62,443.574,389.641z"/>
        <path style="fill:#3E8FC9;" d="M415.258,314.012c-59.878,89.557-172.127,120.832-237.872,131.601
            c9.225,21.947,34.495,32.256,56.441,23.031c5.025-2.109,9.607-5.163,13.494-8.982L266.9,512l146.241-55.079
            c26.945-10.205,40.552-40.283,30.434-67.254L415.258,314.012z"/>
        <path style="fill:#80A834;" d="M229.966,60.746c-5.979,0.009-11.889,1.258-17.356,3.662V51.816
            C147.63,111.633,59.592,144.67,4.339,160.768v47.503h64.408c-9.633-21.938,0.338-47.538,22.285-57.17
            c21.938-9.633,47.538,0.338,57.17,22.285c4.886,11.116,4.886,23.778,0,34.894h64.408v-64.408
            c21.938,9.633,47.538-0.338,57.17-22.285s-0.338-47.538-22.285-57.17C241.976,61.978,236.006,60.737,229.966,60.746z"/>
        <path style="fill:#FEC9A3;" d="M403.682,390.665c-8.305-8.47-21.903-8.609-30.373-0.304c-8.47,8.305-8.609,21.903-0.304,30.373
            c0.104,0.104,0.2,0.208,0.304,0.304l30.217,30.217c0,28.759,23.309,52.068,52.068,52.068l0,0h52.068v-8.678L403.682,390.665z"/>
        </svg>
    {/if}
    {if $svgId == "feed_product"}
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
            stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M12 3l8 4.5l0 9l-8 4.5l-8 -4.5l0 -9l8 -4.5" />
            <path d="M12 12l8 -4.5" />
            <path d="M12 12l0 9" />
            <path d="M12 12l-8 -4.5" />
            <path d="M16 5.25l-8 4.5" />
        </svg>
    {/if}
    {if $svgId == "feed_features"}
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
            stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M4 4h16v2.172a2 2 0 0 1 -.586 1.414l-4.414 4.414v7l-6 2v-8.5l-4.48 -4.928a2 2 0 0 1 -.52 -1.345v-2.227" />
        </svg>
    {/if}
    {if $svgId == "feed_category"}
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
            stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M4 5a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v4a1 1 0 0 1 -1 1h-4a1 1 0 0 1 -1 -1l0 -4" />
            <path d="M14 5a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v4a1 1 0 0 1 -1 1h-4a1 1 0 0 1 -1 -1l0 -4" />
            <path d="M4 15a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v4a1 1 0 0 1 -1 1h-4a1 1 0 0 1 -1 -1l0 -4" />
            <path d="M14 15a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v4a1 1 0 0 1 -1 1h-4a1 1 0 0 1 -1 -1l0 -4" />
        </svg>
    {/if}
    {if $svgId == "feed_settings"}
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
            stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M9 15l6 -6" />
            <path d="M11 6l.463 -.536a5 5 0 0 1 7.071 7.072l-.534 .464" />
            <path d="M13 18l-.397 .534a5.068 5.068 0 0 1 -7.127 0a4.972 4.972 0 0 1 0 -7.071l.524 -.463" />
        </svg>
    {/if}
    {if $svgId == "backup"}
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
            stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M4 6a8 3 0 1 0 16 0a8 3 0 1 0 -16 0" />
            <path d="M4 6v6a8 3 0 0 0 16 0v-6" />
            <path d="M4 12v6a8 3 0 0 0 16 0v-6" />
        </svg>
    {/if}
    {if $svgId == "upload"}
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
            stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="dropzone_icon">
            <path d="M7 18a4.6 4.4 0 0 1 0 -9a5 4.5 0 0 1 11 2h1a3.5 3.5 0 0 1 0 7h-1" />
            <path d="M9 15l3 -3l3 3" />
            <path d="M12 12l0 9" />
        </svg>
    {/if}
{/strip}
