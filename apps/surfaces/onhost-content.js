// Onhost — obsah blogu a knowledgebase (výpisy i detaily článků).

export function contentPages(cs) {
  const _ = (a, b) => (cs ? a : b);

  const blog = [
    {
      slug: 'pue-118', cat: _('Provoz', 'Operations'), date: '18. 8. 2026', read: '11 min', featured: true,
      title: _('Jak jsme dostali PUE v Praze na 1,18', 'How we got Prague PUE down to 1.18'),
      excerpt: _('Dva roky měření, tři chyby a jedna změna v řízení ventilátorů, která ušetřila víc než všechno ostatní dohromady.', 'Two years of measurement, three mistakes and one fan-control change that saved more than everything else combined.'),
      author: _('Marek Šimek, NOC', 'Marek Šimek, NOC'),
      body: [
        ['', _('Když jsme v roce 2023 spouštěli PRG2, měli jsme PUE 1,54. To je průměr evropského datacentra a znamená to, že na každý kilowatt spotřebovaný serverem padne dalšího půl kilowattu na chlazení, ztráty v UPS a osvětlení. Za rok to u našeho odběru dělalo přes tři miliony korun, které nešly na výpočetní výkon.', 'When we brought PRG2 online in 2023 our PUE was 1.54. That is the European data centre average, and it means every kilowatt a server draws costs another half kilowatt in cooling, UPS losses and lighting. At our draw that was over three million crowns a year not spent on compute.')],
        [_('První chyba: měřili jsme špatně', 'Mistake one: we measured the wrong thing'), _('Prvních šest měsíců jsme počítali PUE z fakturace za celou budovu, včetně kanceláří a serverovny pro vlastní vývoj. Číslo bylo optimistické o osm procent. Než začnete cokoli optimalizovat, potřebujete měřit odběr na úrovni rozvaděče a v intervalu, který zachytí špičky. My měříme po minutě a data držíme deset let.', 'For the first six months we calculated PUE from the whole building bill, offices and our own dev room included. The number was eight percent too optimistic. Before you optimise anything you need per-rack metering at an interval that catches peaks. We measure every minute and keep the data for ten years.')],
        [_('Co skutečně pomohlo', 'What actually helped'), _('Zvýšení teploty v sále z 19 na 22 stupňů přineslo 0,11 PUE. Uzavření teplých uliček dalších 0,09. Ale největší skok, 0,14, přinesla změna řízení ventilátorů: místo konstantních otáček je řídíme podle rozdílu teplot na vstupu a výstupu. Trvalo to tři týdny práce a nestálo to nic než čas.', 'Raising the hall from 19 to 22 degrees gave us 0.11 PUE. Containing the hot aisles gave another 0.09. But the biggest single jump, 0.14, came from fan control: instead of constant speed we drive them from the inlet-to-outlet temperature delta. It took three weeks of work and cost nothing but time.')],
        [_('Co nepomohlo', 'What did not help'), _('Vyměnili jsme UPS za modernější s vyšší účinností v částečném zatížení. Papírově to mělo dát 0,04, reálně jsme naměřili 0,01. Návratnost přes dvacet let. Kdybychom to počítali předem pořádně, nekoupili bychom to.', 'We swapped the UPS for a newer unit with better part-load efficiency. On paper it should have given 0.04; we measured 0.01. Payback over twenty years. Had we done the maths properly first, we would not have bought it.')],
        [_('Kde jsme teď', 'Where we are now'), _('Roční průměr za 2025 je 1,18, v zimě se dostáváme pod 1,12 díky volnému chlazení. Odpadní teplo posíláme do dvou sousedních budov, což se do PUE nepočítá, ale do faktury sousedů ano.', 'The 2025 annual average is 1.18, and in winter free cooling takes us below 1.12. Waste heat goes to two neighbouring buildings, which does not count towards PUE but does show up on their bills.')]
      ]
    },
    {
      slug: 'ddos-300', cat: 'Postmortem', date: '2. 8. 2026', read: '9 min',
      title: _('Postmortem: 42 Gbps na herní servery a šest minut, které to trvalo', 'Postmortem: 42 Gbps at the game servers and the six minutes it lasted'),
      excerpt: _('Útok začal v neděli ve 20:14, hráči si ho nevšimli, ale my ano. Časová osa, příčina a co jsme změnili.', 'The attack started at 20:14 on a Sunday. Players never noticed; we did. Timeline, cause and what we changed.'),
      author: _('Ondřej Vrána, síťový tým', 'Ondřej Vrána, network team'),
      body: [
        ['', _('V neděli 2. srpna ve 20:14 dorazil na naši síť UDP amplifikační útok o objemu 42 Gbps mířený na dvanáct herních serverů. Mitigace nasadila filtr do tří sekund, ale prvních devadesát sekund měla část hráčů na dvou serverech vyšší ztrátovost paketů.', 'On Sunday 2 August at 20:14 a 42 Gbps UDP amplification attack hit our network, aimed at twelve game servers. Mitigation applied a filter within three seconds, but for the first ninety seconds some players on two servers saw elevated packet loss.')],
        [_('Časová osa', 'Timeline'), _('20:14:02 detekce podle nárůstu paketů za sekundu. 20:14:05 automatický filtr na hraničních routerech. 20:14:31 útok mění vektor na TCP SYN. 20:15:40 druhý filtr, ztrátovost padá na nulu. 20:20:11 útok končí. 20:41 zveřejňujeme incident na status stránce.', '20:14:02 detection from the packets-per-second jump. 20:14:05 automatic filter on the border routers. 20:14:31 the attack switches vector to TCP SYN. 20:15:40 second filter, loss drops to zero. 20:20:11 the attack ends. 20:41 we publish the incident on the status page.')],
        [_('Příčina zpoždění', 'Why the delay'), _('Náš profil pro herní provoz čekal na potvrzení vzorku po dobu 60 sekund, aby nefiltroval legitimní špičku při startu turnaje. U změny vektoru to znamenalo, že druhý filtr přišel o minutu později, než mohl.', 'Our game-traffic profile waited 60 seconds for sample confirmation so it would not filter a legitimate tournament spike. When the vector changed, that meant the second filter arrived a minute later than it could have.')],
        [_('Co jsme změnili', 'What we changed'), _('Zkrátili jsme potvrzovací okno na 15 sekund a přidali pravidlo, které při už probíhající mitigaci potvrzení přeskočí úplně. Otestovali jsme to na kopii provozu z tohoto útoku. Kdyby přišel dnes, ztrátovost by trvala jedenáct sekund místo devadesáti.', 'We shortened the confirmation window to 15 seconds and added a rule that skips confirmation entirely while a mitigation is already running. We replayed this attack against the new profile: today the loss would last eleven seconds instead of ninety.')]
      ]
    },
    {
      slug: 'postgres-tuning', cat: _('Databáze', 'Databases'), date: '24. 7. 2026', read: '14 min',
      title: _('Tři dotazy, které zpomalovaly e-shop o 400 ms', 'Three queries that were costing a store 400 ms'),
      excerpt: _('Případ z provozu: WooCommerce s dvanácti tisíci produkty, pomalá pokladna a index, který nikdo nečekal.', 'A field case: WooCommerce with twelve thousand products, a slow checkout and an index nobody expected.'),
      author: _('Petra Kolářová, platform tým', 'Petra Kolářová, platform team'),
      body: [
        ['', _('Zákazník si stěžoval, že pokladna trvá tři sekundy. Server nebyl vytížený, CPU na dvaceti procentech, disk se nudil. Zapnuli jsme pg_stat_statements a nechali to týden běžet.', 'The customer complained that checkout took three seconds. The server was not busy: CPU at twenty percent, disk idle. We turned on pg_stat_statements and let it run for a week.')],
        [_('Co data ukázala', 'What the data showed'), _('Devadesát procent času padlo na tři dotazy. Dva z nich generoval plugin pro dopravu, který při každém přepočtu košíku procházel celou tabulku objednávek. Třetí byl náš oblíbený: COUNT(*) nad postmeta bez podmínky, volaný v hlavičce na každé stránce.', 'Ninety percent of the time went to three queries. Two came from a shipping plugin that scanned the whole orders table on every basket recalculation. The third was an old favourite: an unconditional COUNT(*) over postmeta, called in the header on every page.')],
        [_('Oprava', 'The fix'), _('Dva částečné indexy, jeden materializovaný pohled obnovovaný po minutě a cache na patnáct sekund pro počítadlo v hlavičce. Doba pokladny klesla z 3,1 s na 0,4 s. Nezvětšovali jsme server.', 'Two partial indexes, one materialised view refreshed every minute, and a fifteen-second cache for the header counter. Checkout went from 3.1 s to 0.4 s. We did not resize the server.')],
        [_('Co si z toho odnést', 'The takeaway'), _('Než přidáte jádra, změřte dotazy. Za osm let jsme nezažili případ, kdy by pomalý e-shop potřeboval větší stroj dřív než lepší index. Konfigurace pg_stat_statements i obě definice indexů jsou v našem repozitáři s příklady.', 'Before adding cores, measure the queries. In eight years we have not seen a slow store that needed a bigger machine before it needed a better index. The pg_stat_statements config and both index definitions are in our examples repo.')]
      ]
    },
    {
      slug: 'gpu-cena', cat: 'AI', date: '11. 7. 2026', read: '10 min',
      title: _('Kolik doopravdy stojí hodina H100 v Praze', 'What an H100 hour in Prague really costs'),
      excerpt: _('Rozpis od nákupní ceny karty přes energii a chlazení po obsazenost. Bez marketingu, s tabulkou.', 'A breakdown from card purchase through power and cooling to utilisation. No marketing, with a table.'),
      author: _('Tomáš Bednář, infrastruktura', 'Tomáš Bednář, infrastructure'),
      body: [
        ['', _('Účtujeme 39 Kč za hodinu H100 80 GB. Často se nás ptají, jestli je to udržitelné, tak jsme rozpis zveřejnili celý.', 'We charge 39 CZK per H100 80 GB hour. People often ask whether that is sustainable, so we published the whole breakdown.')],
        [_('Pořizovací cena', 'Acquisition'), _('Karta se serverovou částí, sítí a podílem na úložišti vychází na 680 tisíc korun. Odepisujeme na 48 měsíců, počítáme s osmdesátiprocentní obsazeností. To je 20 Kč na hodinu.', 'The card with its server share, networking and storage allocation comes to 680 thousand crowns. We write it off over 48 months at eighty percent utilisation. That is 20 CZK an hour.')],
        [_('Energie a chlazení', 'Power and cooling'), _('Karta v zátěži bere 700 W, se serverem a podílem na chlazení počítáme 1,05 kW. Při naší ceně energie a PUE 1,18 to dělá 7 Kč na hodinu.', 'Under load the card draws 700 W; with the server and cooling share we budget 1.05 kW. At our energy price and 1.18 PUE that is 7 CZK an hour.')],
        [_('Zbytek', 'The rest'), _('Prostor v racku, konektivita, dohled a podpora dohromady 6 Kč. Zbývá 6 Kč marže na hodinu, ze které platíme vývoj platformy a riziko neobsazenosti. Když obsazenost spadne pod 60 %, je to ztrátové — proto ukazujeme volnou kapacitu v reálném čase.', 'Rack space, connectivity, monitoring and support come to 6 CZK together. That leaves 6 CZK of margin per hour, which pays for platform development and the risk of idle cards. Below 60% utilisation it loses money — which is why we show free capacity in real time.')]
      ]
    },
    {
      slug: 'bess-praha', cat: _('Energetika', 'Energy'), date: '28. 6. 2026', read: '13 min',
      title: _('Bateriové úložiště jako záloha datacentra i služba síti', 'Battery storage as data centre backup and a grid service'),
      excerpt: _('Dvě megawatthodiny v Praze, které drží sál i vyrovnávají špičky v distribuční síti. Jak se to řídí.', 'Two megawatt-hours in Prague that back the hall and shave peaks on the distribution grid. How it is controlled.'),
      author: _('Marek Šrámek, energetika', 'Marek Šrámek, energy'),
      body: [
        ['', _('Baterie v datacentru se tradičně nabije a čeká na výpadek, který nepřijde. To je drahá pojistka. Naše dvě megawatthodiny dělají obojí: drží sál do naběhnutí agregátu a mezitím obchodují s výkonem.', 'A data centre battery is traditionally charged and then waits for an outage that never comes. That is an expensive insurance policy. Our two megawatt-hours do both: they hold the hall until the gensets start, and trade power in between.')],
        [_('Bezpečnostní rezerva', 'The reserve'), _('Šedesát procent kapacity je vždy nedotknutelných. Řídicí systém nesmí spadnout pod tuto hranici, ať se na spotovém trhu děje cokoli. Tohle pravidlo je natvrdo v regulátoru, ne v aplikační logice.', 'Sixty percent of capacity is untouchable. The control system may never dip below it, whatever the spot market is doing. That rule lives in the controller firmware, not in application logic.')],
        [_('Řízení', 'Control'), _('Zbývajících čtyřicet procent řídí smyčka, která se každou sekundu rozhoduje podle spotové ceny, předpovědi výroby z naší FVE a aktuálního odběru sálu. Běží aktivně ve dvou lokalitách a odezva je pod 50 ms.', 'The remaining forty percent is driven by a loop that decides every second from the spot price, our solar forecast and the hall draw. It runs active-active across two sites with sub-50 ms response.')],
        [_('Výsledek za rok', 'A year in'), _('Baterie ušetřila 1,9 milionu korun na špičkách a dvakrát držela sál při výpadku v distribuci, než naskočil agregát. Návratnost investice vychází na 6,4 roku, což je o dva roky lepší, než jsme počítali.', 'The battery saved 1.9 million crowns on peaks and twice held the hall during a distribution outage before the gensets picked up. Payback works out at 6.4 years, two years better than we modelled.')]
      ]
    },
    {
      slug: 'migrace-z-cloudu', cat: _('Případová studie', 'Case study'), date: '14. 6. 2026', read: '12 min',
      title: _('Skladomat: z velkého cloudu na osm strojů a třetinovou fakturu', 'Skladomat: off a hyperscaler onto eight machines and a third of the bill'),
      excerpt: _('Co přenos obnášel, co se pokazilo a kde bylo levněji zůstat. Se souhlasem zákazníka a s čísly.', 'What the move involved, what broke and where staying was cheaper. With the customer\u2019s consent and the numbers.'),
      author: _('Petra Kolářová, platform tým', 'Petra Kolářová, platform team'),
      body: [
        ['', _('Skladomat provozuje skladový systém pro sto padesát e-shopů. Ve velkém cloudu platili 214 tisíc měsíčně, z toho 38 tisíc jen za odchozí data. Přechod trval jedenáct týdnů.', 'Skladomat runs a warehouse system for a hundred and fifty online stores. Their hyperscaler bill was 214 thousand a month, 38 thousand of it egress alone. The move took eleven weeks.')],
        [_('Co se přeneslo hladce', 'What moved smoothly'), _('Aplikace v kontejnerech, PostgreSQL s replikou a objektové úložiště. Terraform popisoval skoro celé prostředí, takže se dalo přepsat na náš provider za dva dny.', 'Containerised applications, PostgreSQL with a replica and object storage. Terraform described almost the whole environment, so rewriting it for our provider took two days.')],
        [_('Co se pokazilo', 'What broke'), _('Fronta zpráv používala proprietární službu s exactly-once sémantikou, kterou jsme neuměli nahradit jedna ku jedné. Řešili jsme to idempotencí na straně aplikace a týden jsme běželi paralelně, než jsme si byli jistí.', 'Their message queue relied on a proprietary exactly-once service we could not replace one for one. We solved it with application-side idempotency and ran in parallel for a week before we were confident.')],
        [_('Kde bylo levněji zůstat', 'Where staying was cheaper'), _('Analytický warehouse nad dvěma petabajty jsme jim nechali tam, kde byl. Přenos dat by stál víc než roční úspora a nemáme pro to lepší nástroj. Doporučit odchod od nás není prohra.', 'We left their two-petabyte analytics warehouse where it was. Moving the data would cost more than a year of savings and we have no better tool for it. Recommending against a move is not a loss.')],
        [_('Výsledek', 'The outcome'), _('Faktura klesla na 71 tisíc měsíčně, latence do ČR ze 42 na 8 ms. Odchozí data u nás neúčtujeme, takže ta položka zmizela úplně.', 'The bill dropped to 71 thousand a month and Czech latency from 42 to 8 ms. We do not bill egress, so that line vanished entirely.')]
      ]
    },
    {
      slug: 'nvme-zivotnost', cat: 'Hardware', date: '30. 5. 2026', read: '8 min',
      title: _('Čtyři roky NVMe v provozu: kolik disků nám doopravdy umřelo', 'Four years of NVMe in production: how many drives actually died'),
      excerpt: _('Data z 3 400 serverů, rozdíly mezi výrobci a jeden model, který jsme přestali kupovat.', 'Data from 3,400 servers, differences between vendors and one model we stopped buying.'),
      author: _('Tomáš Bednář, infrastruktura', 'Tomáš Bednář, infrastructure'),
      body: [
        ['', _('Za čtyři roky jsme vyměnili 61 NVMe disků z 6 800 v provozu. To je roční míra selhání 0,22 procenta, výrazně lepší, než sliboval datasheet.', 'In four years we replaced 61 NVMe drives out of 6,800 in service. That is a 0.22 percent annual failure rate, considerably better than the datasheets promised.')],
        [_('Rozdíly mezi modely', 'Differences between models'), _('Tři čtvrtiny selhání připadly na jednu řadu od jednoho výrobce, která tvořila jen dvacet procent flotily. Přestali jsme ji kupovat a míra selhání spadla na 0,09 procenta.', 'Three quarters of the failures came from one line from one vendor that made up only twenty percent of the fleet. We stopped buying it and the failure rate fell to 0.09 percent.')],
        [_('Co selhání předpovídá', 'What predicts a failure'), _('Ne opotřebení buněk, ale rychlý nárůst korigovatelných chyb v SMART za posledních 72 hodin. Sledujeme to a disk vyměníme dřív, než dojde k výpadku. Za poslední rok jsme takto předešli 19 selháním.', 'Not cell wear, but a fast rise in correctable SMART errors over the previous 72 hours. We watch that and swap the drive before it fails. Last year that pre-empted 19 failures.')]
      ]
    },
    {
      slug: 'cены-energie', cat: _('Energetika', 'Energy'), date: '19. 5. 2026', read: '9 min',
      title: _('Proč jsme nezdražili, když šla energie nahoru', 'Why we did not raise prices when energy went up'),
      excerpt: _('Fixace, vlastní výroba a snížení PUE. Rozpis, kolik z toho pokryla která část, včetně toho, co nevyšlo.', 'Hedging, our own generation and a lower PUE. A breakdown of what covered what, including what did not work.'),
      author: _('Hana Nováková, vedení', 'Hana Nováková, management'),
      body: [
        ['', _('V roce 2025 vzrostla velkoobchodní cena elektřiny o 34 procent. Naše ceny hostingu zůstaly stejné. Nejde o dobročinnost, ale o tři rozhodnutí učiněná dřív.', 'In 2025 wholesale electricity rose 34 percent. Our hosting prices did not move. That is not charity — it is three decisions made earlier.')],
        [_('Fixace na tři roky', 'A three-year hedge'), _('Šedesát procent odběru máme fixováno do konce roku 2027. Pokrylo to zhruba polovinu nárůstu. Nevýhoda: když cena klesne, platíme víc než trh.', 'Sixty percent of our draw is hedged to the end of 2027. That covered roughly half the increase. The downside: when prices fall we pay above market.')],
        [_('Vlastní výroba a baterie', 'Own generation and storage'), _('FVE o výkonu 840 kWp a bateriové úložiště pokryly další čtvrtinu. Baterie vydělává hlavně na rozdílu mezi ranní a odpolední cenou.', 'An 840 kWp solar array and the battery covered another quarter. The battery earns mostly on the gap between morning and afternoon prices.')],
        [_('Zbytek zaplatilo PUE', 'PUE paid the rest'), _('Snížení PUE z 1,32 na 1,18 znamenalo o jedenáct procent nižší spotřebu při stejném výkonu. Tohle byla nejlevnější část a měli jsme s ní začít dřív.', 'Cutting PUE from 1.32 to 1.18 meant eleven percent less consumption for the same compute. That was the cheapest lever and we should have pulled it sooner.')]
      ]
    },
    {
      slug: 'ipv6-only', cat: _('Sítě', 'Networking'), date: '6. 5. 2026', read: '10 min',
      title: _('Rok provozu IPv6-only serverů: co ještě nefunguje', 'A year of IPv6-only servers: what still does not work'),
      excerpt: _('Nabídli jsme levnější tarif bez veřejné IPv4. Vzalo si ho 340 zákazníků a devět z nich se vrátilo. Proč.', 'We offered a cheaper plan with no public IPv4. 340 customers took it and nine came back. Here is why.'),
      author: _('Ondřej Vrána, síťový tým', 'Ondřej Vrána, network team'),
      body: [
        ['', _('Veřejná IPv4 adresa nás stojí 45 korun měsíčně a zdražuje. Loni jsme spustili tarify bez ní, s NAT64 branou pro odchozí provoz. Za rok si je vzalo 340 zákazníků.', 'A public IPv4 address costs us 45 crowns a month and is getting dearer. Last year we launched plans without one, using a NAT64 gateway for outbound traffic. In a year 340 customers took them.')],
        [_('Co funguje bez problémů', 'What works without trouble'), _('Web za naším reverzním proxy, e-mail přes náš relay, CI/CD, kontejnerové registry, GitHub, většina API. Devadesát procent zákazníků nepoznalo rozdíl.', 'A website behind our reverse proxy, mail via our relay, CI/CD, container registries, GitHub, most APIs. Ninety percent of customers noticed no difference.')],
        [_('Co nefunguje', 'What does not'), _('Připojení k systémům třetích stran, které mají whitelist na IPv4 adresu. Typicky banky, platební brány a starší firemní VPN. Devět zákazníků se kvůli tomu vrátilo na tarif s IPv4.', 'Connecting to third-party systems that whitelist an IPv4 address. Typically banks, payment gateways and older corporate VPNs. Nine customers moved back to an IPv4 plan for that reason.')],
        [_('Doporučení', 'Our recommendation'), _('Pokud provozujete web, API nebo herní server, IPv6-only vám ušetří peníze a nic nerozbije. Pokud voláte do banky nebo do systému, který si vede whitelist, kupte si IPv4 a neřešte to.', 'If you run a website, an API or a game server, IPv6-only saves money and breaks nothing. If you call a bank or anything with a whitelist, buy the IPv4 and move on.')]
      ]
    },
    {
      slug: 'inference-eu', cat: 'AI', date: '22. 4. 2026', read: '11 min',
      title: _('Inference bez logování promptů: jak to technicky vypadá', 'Inference without prompt logging: what that means technically'),
      excerpt: _('Slíbit „nelogujeme prompty“ je snadné. Tady je, co jsme museli vypnout a jak se to dá ověřit.', 'Promising “we do not log prompts” is easy. Here is what we had to switch off and how you can verify it.'),
      author: _('Petra Kolářová, platform tým', 'Petra Kolářová, platform team'),
      body: [
        ['', _('Většina inference platforem prompty loguje, minimálně pro ladění. My ne, a nebylo to zadarmo — přišli jsme o pohodlný způsob, jak hledat chyby.', 'Most inference platforms log prompts, at least for debugging. We do not, and it was not free: we lost the convenient way to chase bugs.')],
        [_('Co jsme museli vypnout', 'What we switched off'), _('Přístupové logy s tělem požadavku, trasování s obsahem zpráv, cache odpovědí na disku a metriky, které braly prvních sto znaků promptu jako popisek.', 'Access logs with request bodies, tracing with message contents, on-disk response caching, and metrics that used the first hundred characters of the prompt as a label.')],
        [_('Co místo toho měříme', 'What we measure instead'), _('Počty tokenů, latenci, chybovost a identifikátor modelu. Na ladění stačí, jen to trvá déle a občas potřebujeme, aby nám zákazník problém zreprodukoval.', 'Token counts, latency, error rates and the model identifier. Enough to debug, though it takes longer and sometimes we need the customer to reproduce the issue.')],
        [_('Jak si to ověříte', 'How to verify it'), _('Konfigurace proxy i vLLM je součástí smlouvy jako příloha a na vyžádání pustíme auditora k živému systému. Nemáme jak dokázat negativní tvrzení jinak než přístupem.', 'The proxy and vLLM configuration is an annex to the contract, and on request we let an auditor onto the live system. There is no other way to prove a negative than access.')]
      ]
    },
    {
      slug: 'onboarding-noc', cat: _('Provoz', 'Operations'), date: '8. 4. 2026', read: '7 min',
      title: _('Jak zaučujeme nové lidi do NOC', 'How we onboard new NOC engineers'),
      excerpt: _('Šest týdnů, dvanáct cvičných incidentů a jeden pátek, kdy poprvé sedí na telefonu sami.', 'Six weeks, twelve rehearsed incidents and one Friday when they take the phone alone.'),
      author: _('Marek Šimek, NOC', 'Marek Šimek, NOC'),
      body: [
        ['', _('Nováček u nás nesmí sáhnout na produkci první tři týdny. Ne proto, že bychom mu nevěřili, ale protože ještě nezná, co je normální.', 'A new hire does not touch production for the first three weeks. Not from mistrust, but because they do not yet know what normal looks like.')],
        [_('Cvičné incidenty', 'Rehearsed incidents'), _('Máme dvanáct scénářů na kopii produkce: plný disk, rozbitá replikace, DDoS, chybná konfigurace firewallu. Každý má runbook, ale schválně ne úplný.', 'We have twelve scenarios on a copy of production: full disk, broken replication, DDoS, a bad firewall change. Each has a runbook, deliberately incomplete.')],
        [_('První služba', 'First shift'), _('Šestý týden sedí na telefonu s kolegou vedle sebe. Osmý týden sám, ale s eskalací na jedno tlačítko. Za čtyři roky jsme takto zaučili jedenáct lidí a devět jich tu pořád je.', 'In week six they take the phone with a colleague beside them. In week eight alone, with one-button escalation. In four years we have onboarded eleven people this way and nine are still here.')]
      ]
    },
    {
      slug: 'ceny-transparentne', cat: _('Ceny', 'Pricing'), date: '25. 3. 2026', read: '8 min',
      title: _('Proč u nás nenajdete cenu „od“ s hvězdičkou', 'Why you will not find an asterisk on our prices'),
      excerpt: _('Rozhodnutí neúčtovat odchozí data, nepodmiňovat slevy dvouletým závazkem a nezdražovat po prvním roce.', 'The decision not to bill egress, not to gate discounts behind two-year terms, and not to raise prices after year one.'),
      author: _('Hana Nováková, vedení', 'Hana Nováková, management'),
      body: [
        ['', _('Zaváděcí cena, která se po roce ztrojnásobí, je v hostingu standard. Nám přijde, že je to způsob, jak si koupit zákazníka, který by jinak nepřišel.', 'An introductory price that triples after a year is standard in hosting. To us it looks like a way to buy a customer who would not otherwise come.')],
        [_('Co to znamená v číslech', 'What that means in numbers'), _('Naše ceny jsou o 10 až 20 procent vyšší než nejlevnější zaváděcí nabídky na trhu a o 40 až 70 procent nižší než jejich ceny po prvním roce. Kdo počítá na tři roky, vyjde u nás líp.', 'Our prices are 10 to 20 percent above the cheapest introductory offers and 40 to 70 percent below what those cost after year one. Anyone doing three-year maths comes out ahead here.')],
        [_('Odchozí data', 'Egress'), _('Neúčtujeme je vůbec. Je to náklad, který neseme, a stojí nás zhruba dvě procenta obratu. Za to, že odejít od nás je levné, platíme rádi.', 'We do not bill it at all. It is a cost we carry, running at roughly two percent of revenue. We are happy to pay for leaving being cheap.')]
      ]
    }
  ];

  const kb = [
    {
      slug: 'prenos-domeny', cat: _('Domény a DNS', 'Domains and DNS'), read: '3 min', updated: '12. 8. 2026',
      title: _('Přenos domény k Onhostu', 'Transferring a domain to Onhost'),
      excerpt: _('Autorizační kód, odemčení domény a co se děje s DNS během přenosu.', 'The auth code, unlocking the domain and what happens to DNS during the transfer.'),
      body: [
        ['', _('Přenos domény trvá u českých koncovek zpravidla několik hodin, u generických do pěti dnů. Web ani e-mail během něj nevypadnou, pokud si nejdřív zkopírujete DNS záznamy.', 'Transfers usually take a few hours for Czech TLDs and up to five days for generic ones. Neither web nor mail goes down during it, provided you copy your DNS records first.')],
        [_('Postup', 'Steps'), _('1. U současného registrátora doménu odemkněte a vyžádejte autorizační kód. 2. V našem panelu zvolte Domény → Přenést a kód vložte. 3. Zkontrolujte předvyplněné DNS záznamy, které jsme načetli z původní zóny. 4. Potvrďte přenos e-mailem, který dorazí na kontakt vlastníka.', '1. Unlock the domain at your current registrar and request the auth code. 2. In our panel choose Domains → Transfer and paste the code. 3. Check the DNS records we prefilled from your existing zone. 4. Confirm the transfer via the email sent to the owner contact.')],
        [_('Časté problémy', 'Common problems'), _('Doména je mladší než 60 dní — přenos nelze provést, počkejte. Kontakt vlastníka má neplatný e-mail — opravte ho u původního registrátora. Doména má nastaveno DNSSEC — vypněte ho před přenosem a u nás znovu zapněte.', 'The domain is under 60 days old — transfers are blocked, wait it out. The owner contact has an invalid email — fix it at the old registrar. DNSSEC is enabled — switch it off before the transfer and back on with us.')]
      ]
    },
    {
      slug: 'ssh-klic', cat: _('VPS a Linux', 'VPS and Linux'), read: '4 min', updated: '3. 8. 2026',
      title: _('Nastavení SSH klíče na VPS', 'Setting up an SSH key on a VPS'),
      excerpt: _('Vygenerování klíče, nahrání do panelu a vypnutí přihlášení heslem.', 'Generating a key, uploading it in the panel and disabling password login.'),
      body: [
        ['', _('Přihlášení heslem doporučujeme vypnout hned po prvním přihlášení. Klíč je bezpečnější a při automatizaci nezbytný.', 'We recommend disabling password login right after your first sign-in. A key is safer and essential for automation.')],
        [_('Vygenerování klíče', 'Generating a key'), _('Na svém počítači spusťte ssh-keygen -t ed25519 -C "vas@email.cz". Klíč uložte na výchozí místo a nastavte heslo k soukromému klíči. Veřejná část je v souboru s příponou .pub.', 'On your machine run ssh-keygen -t ed25519 -C "you@example.com". Save it to the default location and set a passphrase on the private key. The public half is in the .pub file.')],
        [_('Nahrání a test', 'Upload and test'), _('Obsah .pub souboru vložte v panelu do Servery → SSH klíče. Klíč se propíše do všech vašich serverů do třiceti sekund. Otestujte ssh root@adresa a teprve potom vypněte heslo.', 'Paste the .pub contents into Servers → SSH keys in the panel. The key propagates to all your servers within thirty seconds. Test ssh root@address before you disable passwords.')],
        [_('Vypnutí hesla', 'Disabling passwords'), _('V /etc/ssh/sshd_config nastavte PasswordAuthentication no a restartujte službu příkazem systemctl restart ssh. Nechte si otevřené druhé okno s aktivním připojením pro případ, že se něco pokazí.', 'In /etc/ssh/sshd_config set PasswordAuthentication no and restart with systemctl restart ssh. Keep a second session open in case something goes wrong.')]
      ]
    },
    {
      slug: 'wordpress-staging', cat: 'WordPress', read: '4 min', updated: '28. 7. 2026',
      title: _('Staging pro WordPress na dva kliky', 'Two-click WordPress staging'),
      excerpt: _('Vytvoření kopie webu, testování změn a bezpečné nasazení zpět do produkce.', 'Cloning the site, testing changes and merging safely back to production.'),
      body: [
        ['', _('Staging je kopie webu na skryté adrese. Kopie se vytvoří včetně databáze a nahraných souborů, takže se chová jako ostrý web.', 'Staging is a copy of your site on a hidden address. The clone includes the database and uploads, so it behaves like the live site.')],
        [_('Vytvoření', 'Creating it'), _('V panelu u webu zvolte Staging → Vytvořit kopii. Trvá to podle velikosti webu deset sekund až dvě minuty. Kopie má vypnuté indexování i odesílání e-mailů.', 'In the panel choose Staging → Create copy. It takes ten seconds to two minutes depending on size. The copy has indexing and outgoing mail disabled.')],
        [_('Nasazení zpět', 'Merging back'), _('Můžete přenést jen soubory, jen databázi, nebo obojí. U e-shopů přenášejte pouze soubory — objednávky vzniklé mezitím byste přepsali.', 'You can push files only, database only, or both. For online stores push files only — otherwise you would overwrite orders created in the meantime.')]
      ]
    },
    {
      slug: 'deploy-github', cat: _('Git a CI/CD', 'Git and CI/CD'), read: '6 min', updated: '19. 8. 2026',
      title: _('Nasazení z GitHubu za pět minut', 'Deploying from GitHub in five minutes'),
      excerpt: _('Propojení repozitáře, automatická detekce stacku a nastavení proměnných prostředí.', 'Connecting the repo, automatic stack detection and setting environment variables.'),
      body: [
        ['', _('Propojení repozitáře nevyžaduje žádný konfigurační soubor. Stack rozpoznáme podle package.json, requirements.txt, go.mod nebo Dockerfile.', 'Connecting a repo requires no configuration file. We detect the stack from package.json, requirements.txt, go.mod or a Dockerfile.')],
        [_('Postup', 'Steps'), _('1. Panel → Projekty → Nový projekt → GitHub. 2. Povolte přístup k repozitáři (můžete vybrat jen jeden). 3. Vyberte větev, ze které se nasazuje. 4. Doplňte proměnné prostředí. 5. Klikněte na Nasadit.', '1. Panel → Projects → New project → GitHub. 2. Grant access to the repository (a single repo is fine). 3. Pick the branch to deploy from. 4. Add environment variables. 5. Hit Deploy.')],
        [_('Co se stane při každém pushi', 'What happens on every push'), _('Spustí se build na našem runneru, projde se cache, vytvoří se nová verze a přepne se provoz. Předchozích deset verzí zůstává připravených pro okamžitý rollback.', 'A build runs on our runner, the cache is reused, a new release is created and traffic switches over. The previous ten releases stay warm for instant rollback.')],
        [_('Preview prostředí', 'Preview environments'), _('Zapnete jedním přepínačem. Každý pull request pak dostane vlastní URL i kopii databáze se anonymizovanými daty a po merge se sám uklidí.', 'Enable it with one switch. Every pull request then gets its own URL and an anonymised database copy, cleaned up after merge.')]
      ]
    },
    {
      slug: 'spf-dkim-dmarc', cat: _('E-mail', 'Email'), read: '8 min', updated: '11. 7. 2026',
      title: _('SPF, DKIM a DMARC nastavené správně', 'SPF, DKIM and DMARC done properly'),
      excerpt: _('Tři záznamy, které rozhodují o tom, jestli vaše pošta dorazí do schránky nebo do spamu.', 'Three records that decide whether your mail lands in the inbox or in spam.'),
      body: [
        ['', _('Pořadí je důležité: nejdřív SPF, pak DKIM, DMARC až nakonec. Když nastavíte DMARC na reject dřív, než ostatní dvě fungují, přestane vám chodit pošta.', 'Order matters: SPF first, then DKIM, DMARC last. Setting DMARC to reject before the other two work will stop your mail dead.')],
        ['SPF', _('Jeden TXT záznam na kořeni domény, který vyjmenuje servery oprávněné odesílat. Pozor na limit deseti DNS dotazů — každé include se počítá. Pokud používáte Onhost mail i externí nástroj na newslettery, potřebujete oba.', 'One TXT record at the domain root listing the servers allowed to send. Watch the ten-lookup limit — every include counts. If you use Onhost mail plus an external newsletter tool, you need both.')],
        ['DKIM', _('Klíč vygenerujeme v panelu, vy vložíte TXT záznam na selektor. Podpis kontrolujte až po propagaci DNS, jinak dostanete falešně negativní výsledek.', 'We generate the key in the panel and you add a TXT record at the selector. Check the signature only after DNS propagates, otherwise you get a false negative.')],
        ['DMARC', _('Začněte s p=none a rua adresou pro reporty. Po dvou týdnech reportů uvidíte, kdo za vás odesílá. Teprve pak přepněte na quarantine a nakonec na reject.', 'Start with p=none and an rua address for reports. Two weeks of reports will show who sends on your behalf. Only then move to quarantine and finally reject.')]
      ]
    },
    {
      slug: 'obnova-ze-zalohy', cat: _('Začínáme', 'Getting started'), read: '2 min', updated: '2. 8. 2026',
      title: _('Obnova webu ze zálohy', 'Restoring a site from a backup'),
      excerpt: _('Výběr bodu obnovy, obnova jen souborů nebo jen databáze a obnova do kopie.', 'Choosing a restore point, restoring files or database only, and restoring into a copy.'),
      body: [
        ['', _('Zálohy běží automaticky u všech tarifů. Držíme třicet denních bodů, u vyšších tarifů navíc hodinové body za posledních 48 hodin.', 'Backups run automatically on every plan. We keep thirty daily points, and on higher plans hourly points for the last 48 hours.')],
        [_('Obnova', 'Restoring'), _('Panel → Zálohy → vyberte bod → Obnovit. Vždy nabídneme volbu obnovit do kopie, což doporučujeme: původní web zůstane běžet a vy si obnovený zkontrolujete.', 'Panel → Backups → pick a point → Restore. We always offer restoring into a copy, which we recommend: the live site keeps running while you check the restored one.')],
        [_('Obnova jedné tabulky', 'Restoring one table'), _('Napište podpoře, které tabulky potřebujete a z jakého bodu. Provedeme to do třiceti minut, u P1 do patnácti.', 'Message support with the tables you need and the restore point. We do it within thirty minutes, or fifteen for P1.')]
      ]
    },
    {
      slug: 'cron-v-panelu', cat: _('VPS a Linux', 'VPS and Linux'), read: '5 min', updated: '30. 6. 2026',
      title: _('Cron úlohy v panelu i v crontabu', 'Cron jobs in the panel and in crontab'),
      excerpt: _('Kdy použít plánovač v panelu, kdy systémový cron a jak hlídat, že úloha skutečně proběhla.', 'When to use the panel scheduler, when system cron, and how to know the job actually ran.'),
      body: [
        ['', _('Plánovač v panelu je vhodný pro úlohy, které chcete vidět v logu a dostat upozornění, když selžou. Systémový cron je vhodný pro úlohy s vysokou frekvencí.', 'The panel scheduler suits jobs you want logged and alerted on. System cron suits high-frequency jobs.')],
        [_('Panel', 'The panel'), _('Zadáte příkaz, interval a e-mail pro selhání. Výstup se ukládá třicet dní a při nenulovém návratovém kódu přijde upozornění. Minimální interval je jedna minuta.', 'You give a command, an interval and an email for failures. Output is kept for thirty days and a non-zero exit code triggers an alert. The minimum interval is one minute.')],
        [_('Systémový cron', 'System cron'), _('Editujte přes crontab -e. Nezapomeňte na plné cesty k binárkám a na přesměrování výstupu — bez něj se výstup posílá e-mailem uživateli, což na serveru zpravidla nikam nedojde.', 'Edit with crontab -e. Remember full binary paths and output redirection — without it output is mailed to the local user, which on a server usually goes nowhere.')],
        [_('Hlídání běhu', 'Watching the run'), _('Doporučujeme heartbeat: úloha na konci zavolá URL monitoringu. Když volání nepřijde, dostanete alert. V panelu to zapnete jedním přepínačem.', 'We recommend a heartbeat: the job calls a monitoring URL when it finishes. If the call does not arrive, you get an alert. One switch in the panel enables it.')]
      ]
    },
    {
      slug: 'minecraft-modpack', cat: _('Herní servery', 'Game servers'), read: '5 min', updated: '14. 8. 2026',
      title: _('Instalace modpacku z CurseForge', 'Installing a CurseForge modpack'),
      excerpt: _('Nahrání modpacku, nastavení paměti a co dělat, když server po startu spadne.', 'Uploading the pack, setting memory and what to do when the server crashes on boot.'),
      body: [
        ['', _('Modpacky instalujeme z URL nebo ze souboru. Server se po instalaci sám nastartuje s doporučenou pamětí podle počtu modů.', 'We install modpacks from a URL or a file. After installation the server starts itself with recommended memory based on the mod count.')],
        [_('Postup', 'Steps'), _('1. Panel → Server → Modpacky → Instalovat z URL. 2. Vložte odkaz na modpack z CurseForge. 3. Vyberte verzi. 4. Potvrďte přepsání světa, nebo zvolte instalaci vedle stávajícího.', '1. Panel → Server → Modpacks → Install from URL. 2. Paste the CurseForge pack link. 3. Choose the version. 4. Confirm overwriting the world, or install alongside the existing one.')],
        [_('Když server spadne', 'When it crashes'), _('Devět z deseti pádů je nedostatek paměti. V logu hledejte OutOfMemoryError. Paměť navýšíte za provozu v panelu, server se restartuje sám do dvaceti sekund.', 'Nine crashes out of ten are memory. Look for OutOfMemoryError in the log. You can raise memory live in the panel; the server restarts itself within twenty seconds.')]
      ]
    },
    {
      slug: 'faktury-dph', cat: _('Fakturace', 'Billing'), read: '3 min', updated: '5. 8. 2026',
      title: _('Firemní faktury, DPH a kredit', 'Company invoices, VAT and credit'),
      excerpt: _('Nastavení fakturačních údajů, reverse charge pro zahraniční firmy a jak funguje kredit.', 'Setting billing details, reverse charge for foreign companies and how credit works.'),
      body: [
        ['', _('Faktury vystavujeme první pracovní den v měsíci za měsíc předchozí. Splatnost je čtrnáct dnů, u smluvních zákazníků třicet.', 'Invoices are issued on the first business day of the month for the previous month. Terms are fourteen days, or thirty for contract customers.')],
        [_('DPH', 'VAT'), _('Českým firmám i osobám účtujeme 21 %. Firmám z EU s platným DIČ účtujeme v režimu reverse charge bez DPH. DIČ ověřujeme automaticky proti systému VIES.', 'Czech companies and individuals are charged 21%. EU companies with a valid VAT ID are billed under reverse charge without VAT. We validate IDs automatically against VIES.')],
        [_('Kredit', 'Credit'), _('Kredit můžete dobít předem a čerpá se automaticky na cokoli z katalogu. Při dobití od pěti tisíc korun přidáváme deset procent navíc. Nevyčerpaný zůstatek se převádí a vracíme ho na požádání.', 'You can top up credit in advance and it is drawn automatically against anything in the catalogue. Top-ups from five thousand crowns get ten percent extra. Unused balance rolls over and is refundable on request.')]
      ]
    },
    {
      slug: 'h100-inference', cat: _('GPU a AI', 'GPU and AI'), read: '9 min', updated: '20. 8. 2026',
      title: _('Spuštění inference na H100', 'Running inference on an H100'),
      excerpt: _('Od objednávky karty po první token: vLLM, model z HuggingFace a nastavení endpointu.', 'From ordering a card to the first token: vLLM, a HuggingFace model and endpoint setup.'),
      body: [
        ['', _('Kartu dostanete zpravidla do devadesáti sekund od objednávky. Předinstalované jsou CUDA 12, PyTorch a vLLM.', 'A card is usually yours within ninety seconds of ordering. CUDA 12, PyTorch and vLLM come preinstalled.')],
        [_('Stažení modelu', 'Fetching the model'), _('Modely stahujte do /mnt/models, což je NVMe svazek, který přežije restart instance. U větších modelů se vyplatí použít náš mirror HuggingFace — stahuje z Prahy a je zdarma.', 'Download models to /mnt/models, an NVMe volume that survives instance restarts. For larger models use our HuggingFace mirror — it serves from Prague and is free.')],
        [_('Spuštění vLLM', 'Starting vLLM'), _('Použijte přiloženou jednotku systemd, která nastaví správné parametry pro H100 včetně tensor parallelism. Endpoint je pak dostupný na portu 8000 a je OpenAI-kompatibilní.', 'Use the bundled systemd unit, which sets the right H100 parameters including tensor parallelism. The endpoint then listens on port 8000 and is OpenAI-compatible.')],
        [_('Autoscaling z nuly', 'Scale to zero'), _('V panelu zapnete škálování z nuly. Endpoint bez provozu se uspí a první požadavek ho probudí za osm až dvacet sekund podle velikosti modelu. Za uspaný endpoint platíte jen úložiště.', 'Enable scale-to-zero in the panel. An idle endpoint sleeps and the first request wakes it in eight to twenty seconds depending on model size. While asleep you pay only for storage.')]
      ]
    },
    {
      slug: 'firewall-pravidla', cat: _('VPS a Linux', 'VPS and Linux'), read: '6 min', updated: '18. 6. 2026',
      title: _('Firewall: co otevřít a co ne', 'Firewall: what to open and what not to'),
      excerpt: _('Výchozí pravidla, správa přes panel i nftables a nejčastější chyba u databází.', 'Default rules, managing them via the panel or nftables, and the most common database mistake.'),
      body: [
        ['', _('Nový server má otevřený jen SSH port a porty, které jste zvolili při objednávce. Všechno ostatní je zavřené na hraně sítě, ne až na serveru.', 'A new server has only SSH and the ports you chose at order time. Everything else is blocked at the network edge, not on the host.')],
        [_('Nejčastější chyba', 'The most common mistake'), _('Otevření portu databáze do internetu, aby se šlo připojit z počítače. Použijte místo toho SSH tunel nebo naši privátní síť. Devadesát procent kompromitovaných serverů, které jsme řešili, mělo veřejně dostupnou databázi.', 'Opening the database port to the internet so you can connect from your laptop. Use an SSH tunnel or our private network instead. Ninety percent of compromised servers we have handled had a publicly reachable database.')],
        [_('Panel versus nftables', 'Panel versus nftables'), _('Pravidla z panelu se aplikují na hraně a nezatěžují server. Lokální nftables použijte pro jemnější pravidla mezi kontejnery. Obojí funguje současně, pořadí je hrana → host.', 'Panel rules apply at the edge and cost the server nothing. Use local nftables for finer rules between containers. Both work together; the order is edge then host.')]
      ]
    },
    {
      slug: 'prava-tymu', cat: _('Začínáme', 'Getting started'), read: '4 min', updated: '22. 7. 2026',
      title: _('Práva v týmu a servisní účty', 'Team roles and service accounts'),
      excerpt: _('Čtyři role, přiřazení práv po projektech a proč nesdílet přihlašovací údaje.', 'Four roles, per-project permissions and why not to share logins.'),
      body: [
        ['', _('Každý člověk má vlastní účet. Sdílené přihlašovací údaje znemožňují audit a při odchodu člověka nutí měnit heslo všem.', 'Every person gets their own account. Shared logins make auditing impossible and force a password change for everyone when somebody leaves.')],
        [_('Role', 'Roles'), _('Vlastník mění fakturaci a ruší služby. Administrátor spravuje vše kromě fakturace. Vývojář nasazuje a restartuje, ale neruší. Jen čtení vidí metriky a logy.', 'Owner changes billing and cancels services. Administrator manages everything except billing. Developer deploys and restarts but cannot delete. Read-only sees metrics and logs.')],
        [_('Servisní účty', 'Service accounts'), _('Pro CI/CD a skripty vytvořte servisní účet s klíčem omezeným na konkrétní projekt a jen na potřebné operace. Klíč nastavte s platností a rotujte ho.', 'For CI/CD and scripts create a service account with a key scoped to one project and only the operations it needs. Give the key an expiry and rotate it.')]
      ]
    }
  ];

  return { blog, kb };
}
