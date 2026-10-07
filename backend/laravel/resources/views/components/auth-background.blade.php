<div class="agro-bg" aria-hidden="true">
    <svg class="agro-scene" viewBox="0 0 1440 900" preserveAspectRatio="xMidYMax slice" xmlns="http://www.w3.org/2000/svg">
        <defs>
            <linearGradient id="agSky" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%" stop-color="#7cc8f0"/>
                <stop offset="45%" stop-color="#bfe6fb"/>
                <stop offset="78%" stop-color="#ffe9c2"/>
                <stop offset="100%" stop-color="#ffd59a"/>
            </linearGradient>
            <radialGradient id="agSun" cx="50%" cy="50%" r="50%">
                <stop offset="0%" stop-color="#fffbe6"/>
                <stop offset="55%" stop-color="#ffe066"/>
                <stop offset="100%" stop-color="#ffb703"/>
            </radialGradient>
            <radialGradient id="agGlow" cx="50%" cy="50%" r="50%">
                <stop offset="0%" stop-color="#fff1b8" stop-opacity=".85"/>
                <stop offset="100%" stop-color="#fff1b8" stop-opacity="0"/>
            </radialGradient>
            <linearGradient id="agHill1" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%" stop-color="#c6e98f"/><stop offset="100%" stop-color="#9fd667"/>
            </linearGradient>
            <linearGradient id="agHill2" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%" stop-color="#8acb57"/><stop offset="100%" stop-color="#63b03d"/>
            </linearGradient>
            <linearGradient id="agHill3" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%" stop-color="#4fa236"/><stop offset="100%" stop-color="#256f26"/>
            </linearGradient>
            <linearGradient id="agWater" x1="0" y1="0" x2="1" y2="0">
                <stop offset="0%" stop-color="#e3f6ff" stop-opacity="0"/>
                <stop offset="50%" stop-color="#e3f6ff" stop-opacity=".9"/>
                <stop offset="100%" stop-color="#e3f6ff" stop-opacity="0"/>
            </linearGradient>
            <g id="agStalk">
                <path d="M0 0 C 4 -60, 10 -120, 26 -175" fill="none" stroke="#6b8e23" stroke-width="3" stroke-linecap="round"/>
                <path d="M26 -175 c 14 6, 26 18, 30 36" fill="none" stroke="#c9a227" stroke-width="2.5" stroke-linecap="round"/>
                <g fill="#f6c945" stroke="#d29b0f" stroke-width=".8">
                    <ellipse cx="34" cy="-168" rx="5" ry="9" transform="rotate(35 34 -168)"/>
                    <ellipse cx="42" cy="-160" rx="5" ry="9" transform="rotate(45 42 -160)"/>
                    <ellipse cx="48" cy="-150" rx="5" ry="9" transform="rotate(55 48 -150)"/>
                    <ellipse cx="53" cy="-140" rx="4.5" ry="8" transform="rotate(65 53 -140)"/>
                    <ellipse cx="22" cy="-160" rx="5" ry="9" transform="rotate(-15 22 -160)"/>
                    <ellipse cx="26" cy="-148" rx="5" ry="9" transform="rotate(10 26 -148)"/>
                </g>
                <path d="M2 -40 C -30 -70, -50 -95, -62 -130" fill="none" stroke="#43a047" stroke-width="5" stroke-linecap="round"/>
                <path d="M6 -70 C 40 -95, 60 -110, 78 -150" fill="none" stroke="#66bb6a" stroke-width="5" stroke-linecap="round"/>
            </g>
        </defs>

        <rect width="1440" height="900" fill="url(#agSky)"/>

        <g class="ag-sun">
            <g class="ag-rays" stroke="#ffe08a" stroke-width="10" stroke-linecap="round" opacity=".55">
                @for ($i = 0; $i < 12; $i++)
                    @php($a = deg2rad($i * 30))
                    <line x1="{{ 1110 + cos($a) * 95 }}" y1="{{ 190 + sin($a) * 95 }}" x2="{{ 1110 + cos($a) * 130 }}" y2="{{ 190 + sin($a) * 130 }}"/>
                @endfor
            </g>
            <circle cx="1110" cy="190" r="190" fill="url(#agGlow)"/>
            <circle cx="1110" cy="190" r="70" fill="url(#agSun)"/>
        </g>

        <g fill="#ffffff">
            <g class="ag-cloud ag-cloud-1" opacity=".95"><ellipse cx="200" cy="130" rx="80" ry="28"/><ellipse cx="255" cy="112" rx="55" ry="34"/><ellipse cx="160" cy="118" rx="44" ry="24"/></g>
            <g class="ag-cloud ag-cloud-2" opacity=".85"><ellipse cx="560" cy="90" rx="62" ry="22"/><ellipse cx="605" cy="78" rx="44" ry="27"/><ellipse cx="525" cy="82" rx="34" ry="18"/></g>
            <g class="ag-cloud ag-cloud-3" opacity=".8"><ellipse cx="1320" cy="120" rx="58" ry="20"/><ellipse cx="1360" cy="108" rx="40" ry="24"/></g>
        </g>

        <g class="ag-birds" fill="none" stroke="#4a5868" stroke-width="2.4" stroke-linecap="round">
            <path d="M520 220 q 10 -10 20 0 q 10 -10 20 0"/><path d="M565 196 q 7 -7 14 0 q 7 -7 14 0"/><path d="M605 236 q 8 -8 16 0 q 8 -8 16 0"/>
        </g>

        <path d="M0 480 L170 345 L320 440 L510 310 L690 430 L870 330 L1050 450 L1230 350 L1440 460 L1440 900 L0 900 Z" fill="#a9d3c0" opacity=".75"/>
        <path d="M0 510 L150 420 L350 490 L560 400 L760 490 L980 410 L1200 500 L1440 430 L1440 900 L0 900 Z" fill="#8cc4a6" opacity=".8"/>

        <path d="M0 570 C 240 530, 480 600, 720 570 S 1200 530, 1440 570 L1440 900 L0 900 Z" fill="url(#agHill1)"/>
        <path d="M0 575 C 240 535, 480 605, 720 575 S 1200 535, 1440 575" fill="none" stroke="#e4f6c4" stroke-width="3"/>
        <rect class="ag-shimmer" x="0" y="612" width="1440" height="5" fill="url(#agWater)"/>
        <path d="M0 645 C 260 605, 520 685, 780 645 S 1220 605, 1440 655 L1440 900 L0 900 Z" fill="url(#agHill2)"/>
        <path d="M0 650 C 260 610, 520 690, 780 650 S 1220 610, 1440 660" fill="none" stroke="#cdebab" stroke-width="3"/>
        <rect class="ag-shimmer ag-shimmer-2" x="0" y="690" width="1440" height="5" fill="url(#agWater)"/>
        <path d="M0 735 C 300 695, 600 775, 900 735 S 1260 705, 1440 745 L1440 900 L0 900 Z" fill="url(#agHill3)"/>

        <g stroke="#1f6b24" stroke-width="2" opacity=".35">
            @for ($i = -10; $i <= 10; $i++)
                <line x1="{{ 720 + $i * 26 }}" y1="745" x2="{{ 720 + $i * 150 }}" y2="900"/>
            @endfor
        </g>

        <g class="ag-pole" transform="translate(330 470)">
            <g class="ag-signal" fill="none" stroke="#ffffff" stroke-linecap="round">
                <path d="M-30 -40 a 42 42 0 0 1 60 0" stroke-width="4" opacity=".9"/>
                <path d="M-48 -58 a 68 68 0 0 1 96 0" stroke-width="4" opacity=".7"/>
                <path d="M-66 -76 a 94 94 0 0 1 132 0" stroke-width="4" opacity=".5"/>
            </g>
            <rect x="-4" y="-20" width="8" height="300" rx="3" fill="#cfd8dc"/>
            <g transform="translate(0 -18)">
                <polygon points="-62,-6 18,-30 46,-12 -34,12" fill="#1e3a5f" stroke="#90caf9" stroke-width="2"/>
                <path d="M-48 -2 L32 -26 M-34 3 L42 -18 M-30 -14 L-6 6 M-6 -21 L18 0" stroke="#5c8ccf" stroke-width="1.5"/>
            </g>
            <rect x="-26" y="60" width="52" height="64" rx="6" fill="#eceff1" stroke="#b0bec5" stroke-width="2"/>
            <circle cx="-10" cy="80" r="4" fill="#43a047"/><circle cx="4" cy="80" r="4" fill="#ffb300"/>
            <rect x="-14" y="94" width="28" height="18" rx="3" fill="#90a4ae"/>
            <line x1="4" y1="20" x2="62" y2="20" stroke="#b0bec5" stroke-width="4"/>
            <circle cx="62" cy="10" r="9" fill="#cfd8dc"/><path d="M62 10 l 18 -6 M62 10 l -6 -18 M62 10 l -10 14" stroke="#90a4ae" stroke-width="3"/>
            <rect x="-56" y="30" width="22" height="16" rx="3" fill="#37474f"/><circle cx="-45" cy="38" r="5" fill="#81d4fa"/>
            <line x1="-34" y1="38" x2="-4" y2="38" stroke="#b0bec5" stroke-width="3"/>
        </g>

        <g class="ag-stalks ag-stalks-left">
            <use href="#agStalk" x="60" y="905"/><use href="#agStalk" x="110" y="900"/><use href="#agStalk" x="160" y="910"/><use href="#agStalk" x="20" y="915"/>
        </g>
        <g class="ag-stalks ag-stalks-right">
            <use href="#agStalk" x="1280" y="905"/><use href="#agStalk" x="1330" y="900"/><use href="#agStalk" x="1380" y="910"/><use href="#agStalk" x="1230" y="915"/>
        </g>
    </svg>
</div>
