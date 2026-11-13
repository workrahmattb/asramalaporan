<?php

namespace Database\Seeders;

final class SimusSeedData
{
    /** @return list<array{code:string,name:string,gender:string,unit:?string,people:array<string,string>}> */
    public static function asramas(): array
    {
        return json_decode('[
  {
    "code": "PA-GHR",
    "name": "Gharim",
    "gender": "putra",
    "unit": null,
    "people": {
      "simus-musyrif-001": "Ismail",
      "simus-musyrif-002": "Imam Hilmi Khair"
    }
  },
  {
    "code": "PA-NUS",
    "name": "Nusantara",
    "gender": "putra",
    "unit": null,
    "people": {
      "simus-musyrif-003": "Agus Rahman",
      "simus-musyrif-004": "Amiruddin"
    }
  },
  {
    "code": "PA-MSR12",
    "name": "Mesir 1 & 2",
    "gender": "putra",
    "unit": null,
    "people": {
      "simus-musyrif-005": "Ardian Damanik",
      "simus-musyrif-006": "Imam Taufik"
    }
  },
  {
    "code": "PA-MSR34",
    "name": "Mesir 3 & 4",
    "gender": "putra",
    "unit": null,
    "people": {
      "simus-musyrif-007": "Jami\'ul Ulum",
      "simus-musyrif-008": "Miftah Alkhairi"
    }
  },
  {
    "code": "PA-PLS12",
    "name": "Palestina 1 & 2",
    "gender": "putra",
    "unit": null,
    "people": {
      "simus-musyrif-009": "Syafiq Fuadi Nasution",
      "simus-musyrif-010": "Rival Dian Putra"
    }
  },
  {
    "code": "PA-PLS34",
    "name": "Palestina 3 & 4",
    "gender": "putra",
    "unit": null,
    "people": {
      "simus-musyrif-011": "Fauzil Hasdi",
      "simus-musyrif-012": "Kemal Dwi Rahmat"
    }
  },
  {
    "code": "PA-IDN12",
    "name": "Indonesia 1 & 2",
    "gender": "putra",
    "unit": null,
    "people": {
      "simus-musyrif-013": "Mahyori Ulfa Sandi",
      "simus-musyrif-014": "Andika"
    }
  },
  {
    "code": "PA-IDN34",
    "name": "Indonesia 3 & 4",
    "gender": "putra",
    "unit": null,
    "people": {
      "simus-musyrif-015": "Mandri Rustin Saputra",
      "simus-musyrif-016": "Esa Rafli"
    }
  },
  {
    "code": "PA-MKK1",
    "name": "Mekkah 1",
    "gender": "putra",
    "unit": null,
    "people": {
      "simus-musyrif-017": "Subhan Hadiyansah"
    }
  },
  {
    "code": "PA-MKK34",
    "name": "Mekkah 3 & 4",
    "gender": "putra",
    "unit": null,
    "people": {
      "simus-musyrif-018": "Alfi Maarif",
      "simus-musyrif-019": "Afdholi"
    }
  },
  {
    "code": "PA-MDN12",
    "name": "Madinah 1 & 2",
    "gender": "putra",
    "unit": null,
    "people": {
      "simus-musyrif-020": "Agus Damanhuri",
      "simus-musyrif-021": "Arga Putra"
    }
  },
  {
    "code": "PA-MDN3",
    "name": "Madinah 3",
    "gender": "putra",
    "unit": null,
    "people": {
      "simus-musyrif-022": "Ihsanul Hanif"
    }
  },
  {
    "code": "PI-KHD19",
    "name": "Khodijah 1 & 9",
    "gender": "putri",
    "unit": "Khodijah",
    "people": {
      "simus-musyrifah-001": "Halimah, S.Pd"
    }
  },
  {
    "code": "PI-KHD2",
    "name": "Khodijah 2",
    "gender": "putri",
    "unit": "Khodijah",
    "people": {
      "simus-musyrifah-002": "Nesya Adya Putri"
    }
  },
  {
    "code": "PI-KHD34",
    "name": "Khodijah 3 & 4",
    "gender": "putri",
    "unit": "Khodijah",
    "people": {
      "simus-musyrifah-003": "Elsa Febrianti, S.Akun"
    }
  },
  {
    "code": "PI-KHD57",
    "name": "Khodijah 5 & 7",
    "gender": "putri",
    "unit": "Khodijah",
    "people": {
      "simus-musyrifah-004": "Yona Juliani, S.Pd"
    }
  },
  {
    "code": "PI-KHD68",
    "name": "Khodijah 6 & 8",
    "gender": "putri",
    "unit": "Khodijah",
    "people": {
      "simus-musyrifah-005": "Zahratul Jannah, S.Pd"
    }
  },
  {
    "code": "PI-HFS14",
    "name": "Hafshah 1 & 4",
    "gender": "putri",
    "unit": "Hafshah",
    "people": {
      "simus-musyrifah-006": "Jamilatun Hasanah"
    }
  },
  {
    "code": "PI-HFS23",
    "name": "Hafshah 2 & 3",
    "gender": "putri",
    "unit": "Hafshah",
    "people": {
      "simus-musyrifah-007": "Siti Asiyah, S.f.u"
    }
  },
  {
    "code": "PI-AYS101102",
    "name": "Aisyah 101 & 102",
    "gender": "putri",
    "unit": "Aisyah",
    "people": {
      "simus-musyrifah-008": "Ulfi Nursyafitri, S.Pd"
    }
  },
  {
    "code": "PI-AYS103106",
    "name": "Aisyah 103 & 106",
    "gender": "putri",
    "unit": "Aisyah",
    "people": {
      "simus-musyrifah-009": "Amanda Safitri Damanik, S.Pd"
    }
  },
  {
    "code": "PI-AYS107108",
    "name": "Aisyah 107 & 108",
    "gender": "putri",
    "unit": "Aisyah",
    "people": {
      "simus-musyrifah-010": "Zaina Rahayu, S.Sos",
      "simus-musyrifah-011": "Rifa Aprillia"
    }
  },
  {
    "code": "PI-AYS201202",
    "name": "Aisyah 201 & 202",
    "gender": "putri",
    "unit": "Aisyah",
    "people": {
      "simus-musyrifah-012": "Helen Novita Sari, S.Pd"
    }
  },
  {
    "code": "PI-AYS203",
    "name": "Aisyah 203",
    "gender": "putri",
    "unit": "Aisyah",
    "people": {
      "simus-musyrifah-013": "Yeli Asmita, S.Pd"
    }
  },
  {
    "code": "PI-AYS204205",
    "name": "Aisyah 204 & 205",
    "gender": "putri",
    "unit": "Aisyah",
    "people": {
      "simus-musyrifah-014": "Natiara, S.Pd"
    }
  },
  {
    "code": "PI-AYS206207",
    "name": "Aisyah 206 & 207",
    "gender": "putri",
    "unit": "Aisyah",
    "people": {
      "simus-musyrifah-015": "Rima Maulida Putri, S.Pd"
    }
  }
]', true, 512, JSON_THROW_ON_ERROR);
    }
}
