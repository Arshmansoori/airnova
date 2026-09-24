<?php
require_once __DIR__ . '/airnova/core.php';
$stations = [
    // Delhi Central & Landmarks
    [
        'city' => 'Delhi (Mandir Marg)',
        'stationName' => 'CPCB Mandir Marg Command Station',
        'stationCode' => 'DL-MANDIR-01',
        'latitude' => 28.6341,
        'longitude' => 77.2005,
    ],
    [
        'city' => 'JLN Stadium',
        'stationName' => 'CPCB Jawaharlal Nehru Stadium',
        'stationCode' => 'DL-JLN-02',
        'latitude' => 28.5828,
        'longitude' => 77.2344,
    ],
    [
        'city' => 'National Stadium',
        'stationName' => 'CPCB Major Dhyan Chand Stadium',
        'stationCode' => 'DL-DHYANCHAND-03',
        'latitude' => 28.6125,
        'longitude' => 77.2374,
    ],

    // Delhi South
    [
        'city' => 'Sriniwaspuri',
        'stationName' => 'DPCC PGDAV College Sriniwaspuri',
        'stationCode' => 'DL-SRINIWASPURI-04',
        'latitude' => 28.5668,
        'longitude' => 77.2514,
    ],
    [
        'city' => 'R.K. Puram',
        'stationName' => 'DPCC Sector 1 R.K. Puram',
        'stationCode' => 'DL-RKPURAM-05',
        'latitude' => 28.5648,
        'longitude' => 77.1744,
    ],
    [
        'city' => 'Okhla Phase II',
        'stationName' => 'DPCC DITE Okhla Industrial Area',
        'stationCode' => 'DL-OKHLA-06',
        'latitude' => 28.5313,
        'longitude' => 77.2707,
    ],
    [
        'city' => 'Hauz Khas',
        'stationName' => 'DPCC Sri Aurobindo Marg',
        'stationCode' => 'DL-AUROBINDO-07',
        'latitude' => 28.5283,
        'longitude' => 77.1893,
    ],
    [
        'city' => 'Asola / Karni Singh',
        'stationName' => 'DPCC Dr. Karni Singh Range',
        'stationCode' => 'DL-KARNISINGH-08',
        'latitude' => 28.4997,
        'longitude' => 77.2671,
    ],

    // Delhi East
    [
        'city' => 'Anand Vihar',
        'stationName' => 'DPCC Anand Vihar Bus Terminal',
        'stationCode' => 'DL-ANANDVIHAR-09',
        'latitude' => 28.6508,
        'longitude' => 77.3152,
    ],
    [
        'city' => 'Patparganj',
        'stationName' => 'DPCC Mother Dairy Patparganj',
        'stationCode' => 'DL-PATPARGANJ-10',
        'latitude' => 28.6202,
        'longitude' => 77.2877,
    ],
    [
        'city' => 'Shahdara',
        'stationName' => 'DPCC ITI Shahdara Jhilmil',
        'stationCode' => 'DL-SHAHDARA-11',
        'latitude' => 28.6721,
        'longitude' => 77.3138,
    ],
    [
        'city' => 'Sonia Vihar',
        'stationName' => 'DPCC Water Treatment Plant Sonia Vihar',
        'stationCode' => 'DL-SONIAVIHAR-12',
        'latitude' => 28.7101,
        'longitude' => 77.2462,
    ],

    // Delhi West
    [
        'city' => 'Punjabi Bagh',
        'stationName' => 'DPCC West Punjabi Bagh',
        'stationCode' => 'DL-PUNJABIBAGH-13',
        'latitude' => 28.6683,
        'longitude' => 77.1167,
    ],
    [
        'city' => 'Dwarka Sector 8',
        'stationName' => 'DPCC NIMR Dwarka',
        'stationCode' => 'DL-DWARKA-14',
        'latitude' => 28.5769,
        'longitude' => 77.0759,
    ],
    [
        'city' => 'Pusa Road',
        'stationName' => 'DPCC ICAR Pusa Road',
        'stationCode' => 'DL-PUSA-15',
        'latitude' => 28.6370,
        'longitude' => 77.1722,
    ],
    [
        'city' => 'Mundka',
        'stationName' => 'DPCC Mundka Industrial Area',
        'stationCode' => 'DL-MUNDKA-16',
        'latitude' => 28.6824,
        'longitude' => 77.0305,
    ],
    [
        'city' => 'Najafgarh',
        'stationName' => 'DPCC Bramprakash Hospital Najafgarh',
        'stationCode' => 'DL-NAJAFGARH-17',
        'latitude' => 28.5727,
        'longitude' => 76.9334,
    ],

    // Delhi North
    [
        'city' => 'Rohini Sector 16',
        'stationName' => 'DPCC SSCBS Rohini',
        'stationCode' => 'DL-ROHINI-18',
        'latitude' => 28.7327,
        'longitude' => 77.1188,
    ],
    [
        'city' => 'Wazirpur',
        'stationName' => 'DPCC DITE Wazirpur Industrial Area',
        'stationCode' => 'DL-WAZIRPUR-19',
        'latitude' => 28.7005,
        'longitude' => 77.1656,
    ],
    [
        'city' => 'Jahangirpuri',
        'stationName' => 'DPCC ITI Jahangirpuri',
        'stationCode' => 'DL-JAHANGIRPURI-20',
        'latitude' => 28.7330,
        'longitude' => 77.1720,
    ],
    [
        'city' => 'Bawana',
        'stationName' => 'DPCC Pooth Khurd Bawana',
        'stationCode' => 'DL-BAWANA-21',
        'latitude' => 28.7758,
        'longitude' => 77.0463,
    ],
    [
        'city' => 'Alipur',
        'stationName' => 'DPCC Alipur Command Station',
        'stationCode' => 'DL-ALIPUR-22',
        'latitude' => 28.8157,
        'longitude' => 77.1525,
    ],
    [
        'city' => 'Narela',
        'stationName' => 'DPCC Narela Sub-City Station',
        'stationCode' => 'DL-NARELA-23',
        'latitude' => 28.8206,
        'longitude' => 77.1011,
    ],

    // Noida & Greater Noida
    [
        'city' => 'Noida Sector 62',
        'stationName' => 'UPPCB Noida Sector 62 Monitoring Station',
        'stationCode' => 'UP-NOIDA-62',
        'latitude' => 28.6245,
        'longitude' => 77.3577,
    ],
    [
        'city' => 'Noida Sector 125',
        'stationName' => 'UPPCB Noida Express Corridor Station',
        'stationCode' => 'UP-NOIDA-125',
        'latitude' => 28.5448,
        'longitude' => 77.3231,
    ],
    [
        'city' => 'Greater Noida (Pari Chowk)',
        'stationName' => 'UPPCB Knowledge Park Greater Noida',
        'stationCode' => 'UP-GRNOIDA-01',
        'latitude' => 28.4727,
        'longitude' => 77.4820,
    ],

    // Gurugram
    [
        'city' => 'Gurugram Cyber City',
        'stationName' => 'HSPCB Cyber Hub Station Gurugram',
        'stationCode' => 'HR-GURUGRAM-CYBER',
        'latitude' => 28.4950,
        'longitude' => 77.0895,
    ],
    [
        'city' => 'Gurugram Sector 51',
        'stationName' => 'HSPCB Sector 51 Urban Station',
        'stationCode' => 'HR-GURUGRAM-51',
        'latitude' => 28.4227,
        'longitude' => 77.0667,
    ],
    [
        'city' => 'Gurugram Gwal Pahari',
        'stationName' => 'NISE Gwal Pahari Gurugram',
        'stationCode' => 'HR-GWALPAHARI-01',
        'latitude' => 28.4227,
        'longitude' => 77.1489,
    ],

    // Ghaziabad
    [
        'city' => 'Ghaziabad Indirapuram',
        'stationName' => 'UPPCB Indirapuram Air Station',
        'stationCode' => 'UP-INDIRAPURAM-01',
        'latitude' => 28.6462,
        'longitude' => 77.3581,
    ],
    [
        'city' => 'Ghaziabad Vasundhara',
        'stationName' => 'UPPCB Vasundhara Air Station',
        'stationCode' => 'UP-VASUNDHARA-02',
        'latitude' => 28.6603,
        'longitude' => 77.3573,
    ],
    [
        'city' => 'Ghaziabad Sanjay Nagar',
        'stationName' => 'UPPCB Sanjay Nagar Ghaziabad',
        'stationCode' => 'UP-SANJAYNAGAR-03',
        'latitude' => 28.6854,
        'longitude' => 77.4538,
    ],

    // Faridabad
    [
        'city' => 'Faridabad Sector 16A',
        'stationName' => 'HSPCB Sector 16A Faridabad',
        'stationCode' => 'HR-FARIDABAD-16A',
        'latitude' => 28.4088,
        'longitude' => 77.3099,
    ],
    [
        'city' => 'Faridabad Sector 30',
        'stationName' => 'HSPCB Sector 30 NIT Faridabad',
        'stationCode' => 'HR-FARIDABAD-30',
        'latitude' => 28.4417,
        'longitude' => 77.3217,
    ],
];

if (!defined('AIRNOVA_TEST')) airnovaDispatch($stations);
