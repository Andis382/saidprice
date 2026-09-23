<?php

namespace Database\Seeders;

/**
 * What a plumber-electrician in Tirana quotes for, and who for. Prices are euro ranges;
 * each line is [kind, Albanian, English, qty, unit key, [min, max]].
 */
final class DemoCatalog
{
    public const JOBS = [
        ['sq' => 'Ndërrim rubineti kuzhine', 'en' => 'Replace kitchen mixer tap', 'lines' => [
            ['PARTS', 'Rubinet kuzhine me dush', 'Kitchen mixer tap with pull-out spray', 1, null, [55, 85]],
            ['PARTS', 'Zorra fleksibël', 'Flexible hoses', 2, 'pcs', [6, 8]],
            ['LABOUR', 'Punëdore', 'Labour', 1, null, [40, 60]],
        ]],
        ['sq' => 'Montim bojleri 80 L', 'en' => 'Install 80 L water heater', 'lines' => [
            ['PARTS', 'Bojler elektrik 80 L', '80 L electric water heater', 1, null, [165, 220]],
            ['PARTS', 'Valvul sigurie dhe rakorderi', 'Safety valve and fittings', 1, null, [14, 22]],
            ['LABOUR', 'Montim dhe lidhje', 'Fitting and connection', 1, null, [50, 70]],
        ]],
        ['sq' => 'Prizë e re në kuzhinë', 'en' => 'New kitchen socket', 'lines' => [
            ['PARTS', 'Prizë me tokëzim', 'Earthed socket', 2, 'pcs', [6, 9]],
            ['PARTS', 'Kabllo 3×2,5 mm²', '3×2.5 mm² cable', 8, 'm', [1, 2]],
            ['LABOUR', 'Punëdore', 'Labour', 1, null, [30, 45]],
        ]],
        ['sq' => 'Ndriçim LED në sallon', 'en' => 'LED lighting in the living room', 'lines' => [
            ['PARTS', 'Panel LED 60×60, 40 W', '60×60 LED panel, 40 W', 4, 'pcs', [18, 26]],
            ['LABOUR', 'Montim', 'Fitting', 2, 'h', [18, 22]],
        ]],
        ['sq' => 'Zëvendësim sifoni', 'en' => 'Replace sink trap', 'lines' => [
            ['PARTS', 'Sifon lavamani', 'Basin trap', 1, null, [9, 15]],
            ['LABOUR', 'Punëdore', 'Labour', 1, null, [20, 30]],
        ]],
        ['sq' => 'Riparim rrjedhjeje tubi', 'en' => 'Repair leaking pipe', 'lines' => [
            ['PARTS', 'Tub PPR 20 mm', '20 mm PPR pipe', 2, 'm', [3, 5]],
            ['PARTS', 'Rakorderi dhe shirit teflon', 'Fittings and PTFE tape', 1, null, [8, 14]],
            ['LABOUR', 'Riparim', 'Repair', 2, 'h', [20, 25]],
        ]],
        ['sq' => 'Ndërrim WC-je', 'en' => 'Replace toilet', 'lines' => [
            ['PARTS', 'WC me rezervuar', 'Close-coupled toilet', 1, null, [120, 170]],
            ['PARTS', 'Silikon dhe lidhje', 'Silicone and connectors', 1, null, [10, 15]],
            ['LABOUR', 'Heqje e së vjetrës dhe montim', 'Removing the old one and fitting', 1, null, [60, 80]],
        ]],
        ['sq' => 'Kuadër elektrik i ri', 'en' => 'New fuse box', 'lines' => [
            ['PARTS', 'Kuadër 12 module', '12-module consumer unit', 1, null, [38, 52]],
            ['PARTS', 'Automat 16 A', '16 A breaker', 6, 'pcs', [6, 9]],
            ['PARTS', 'Diferencial 40 A', '40 A RCD', 1, null, [32, 42]],
            ['LABOUR', 'Punëdore', 'Labour', 1, null, [90, 130]],
        ]],
        ['sq' => 'Ndërrim çezme banjoje', 'en' => 'Replace bathroom tap', 'lines' => [
            ['PARTS', 'Bateri lavamani', 'Basin mixer tap', 1, null, [32, 48]],
            ['LABOUR', 'Punëdore', 'Labour', 1, null, [25, 35]],
        ]],
        ['sq' => 'Zhbllokim kanali', 'en' => 'Unblock drain', 'lines' => [
            ['LABOUR', 'Zhbllokim me makineri', 'Machine drain unblocking', 1, null, [45, 70]],
        ]],
        ['sq' => 'Instalim kabine dushi', 'en' => 'Install shower enclosure', 'lines' => [
            ['PARTS', 'Kabinë dushi 90×90', '90×90 shower enclosure', 1, null, [190, 260]],
            ['PARTS', 'Bateri dushi termostatike', 'Thermostatic shower mixer', 1, null, [60, 85]],
            ['LABOUR', 'Montim', 'Fitting', 1, null, [90, 120]],
        ]],
        ['sq' => 'Ventilator banjoje', 'en' => 'Bathroom extractor fan', 'lines' => [
            ['PARTS', 'Ventilator me valvul', 'Extractor fan with backdraft valve', 1, null, [24, 34]],
            ['LABOUR', 'Punëdore', 'Labour', 1, null, [30, 40]],
        ]],
        ['sq' => 'Ndërrim ndërprerësish', 'en' => 'Replace light switches', 'lines' => [
            ['PARTS', 'Ndërprerës Legrand', 'Legrand switch', 6, 'pcs', [4, 6]],
            ['LABOUR', 'Punëdore', 'Labour', 1, null, [30, 40]],
        ]],
        ['sq' => 'Valvola radiatori', 'en' => 'Radiator valves', 'lines' => [
            ['PARTS', 'Valvul termostatike radiatori', 'Thermostatic radiator valve', 2, 'pcs', [14, 19]],
            ['LABOUR', 'Punëdore', 'Labour', 1, null, [35, 50]],
        ]],
        ['sq' => 'Kontroll instalimi elektrik', 'en' => 'Electrical installation check', 'lines' => [
            ['LABOUR', 'Kontroll dhe matje', 'Inspection and testing', 1, null, [40, 60]],
        ]],
        ['sq' => 'Lidhje lavastovilje', 'en' => 'Connect dishwasher', 'lines' => [
            ['PARTS', 'Rubinet me dy dalje', 'Two-way valve', 1, null, [9, 13]],
            ['PARTS', 'Zorrë shkarkimi', 'Drain hose', 1, null, [6, 9]],
            ['LABOUR', 'Lidhje', 'Connection', 1, null, [25, 35]],
        ]],
        ['sq' => 'Riparim rezervuari WC', 'en' => 'Repair toilet cistern', 'lines' => [
            ['PARTS', 'Mekanizëm rezervuari', 'Cistern fill and flush set', 1, null, [18, 26]],
            ['LABOUR', 'Punëdore', 'Labour', 1, null, [25, 35]],
        ]],
    ];

    public const UNITS = [
        'pcs' => ['sq' => 'copë', 'en' => 'pcs'],
        'm' => ['sq' => 'm', 'en' => 'm'],
        'h' => ['sq' => 'orë', 'en' => 'h'],
    ];

    public const WHEN = [
        'sq' => ['Të hënën në mëngjes', 'Të martën pasdite', 'Të mërkurën në orën 10', 'Të enjten në mëngjes', 'Të premten pasdite', 'Të shtunën paradite', 'Nesër në mëngjes', 'Javën tjetër'],
        'en' => ['Monday morning', 'Tuesday afternoon', 'Wednesday at 10', 'Thursday morning', 'Friday afternoon', 'Saturday before noon', 'Tomorrow morning', 'Next week'],
    ];

    public const WOMEN = ['Drita', 'Arta', 'Blerina', 'Elona', 'Mirela', 'Anila', 'Entela', 'Valbona', 'Jonida', 'Rezarta', 'Albana', 'Eriola', 'Majlinda', 'Brikena', 'Lindita', 'Fatjona', 'Ornela', 'Teuta'];

    public const MEN = ['Arben', 'Ilir', 'Gëzim', 'Besnik', 'Artan', 'Kastriot', 'Ermal', 'Genti', 'Dritan', 'Fatos', 'Agron', 'Sokol', 'Bledar', 'Edmond', 'Erion', 'Luan', 'Petrit', 'Altin'];

    public const SURNAMES = ['Hoxha', 'Kola', 'Dervishi', 'Leka', 'Gjoka', 'Shehu', 'Basha', 'Çela', 'Dushku', 'Myrtaj', 'Kraja', 'Bushati', 'Lika', 'Mema', 'Nika', 'Prifti', 'Topalli', 'Vata', 'Zeneli', 'Hysa', 'Marku', 'Muça', 'Gjini', 'Deda', 'Sula', 'Bregu', 'Hasani'];

    /** Saved the way he says them. */
    public const TITLED = ['Znj. Basha', 'Z. Leka', 'Znj. Gjini', 'Z. Topalli', 'Znj. Prifti', 'Z. Marku'];

    public const ENGLISH = ['Sarah Collins', 'Mark Evans', 'Luca Bianchi', 'Anna Weber', 'James Walker'];

    public const STREETS = [
        'Rruga Myslym Shyri', 'Rruga e Kavajës', 'Rruga Sami Frashëri', 'Bulevardi Bajram Curri', 'Rruga Him Kolli',
        'Rruga Pjetër Budi', 'Rruga e Durrësit', 'Rruga Qemal Stafa', 'Rruga Ali Demi', 'Rruga Medar Shtylla',
        'Rruga Dritan Hoxha', 'Rruga Siri Kodra', 'Rruga Fortuzi', 'Rruga e Elbasanit', 'Rruga Hoxha Tahsim',
        'Rruga Asim Vokshi', 'Rruga Irfan Tomini', 'Rruga Don Bosko', 'Rruga Ismail Qemali', 'Rruga Abdi Toptani',
        'Rruga Mine Peza', 'Rruga Frosina Plaku', 'Rruga e Barrikadave', 'Rruga Hamdi Sina',
    ];

    public const DECLINE_REASONS = [
        'sq' => ['Shumë shtrenjtë për momentin', 'E bëri një i afërm', 'E shtymë për pranverë', null, null],
        'en' => ['A bit over budget right now', 'Our landlord is sorting it', null],
    ];

    public const LOST_REASONS = ['Nuk u përgjigj më', 'Zgjodhi dikë tjetër', 'Puna u anulua'];
}
