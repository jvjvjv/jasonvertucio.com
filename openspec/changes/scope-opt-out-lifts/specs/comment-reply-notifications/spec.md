## ADDED Requirements

### Requirement: Only the address that opted out can lift it
An opt-out SHALL block every spelling of its mailbox, but SHALL be lifted (by a completed confirmation or a verified signed-in opt-in) only from the same address. Two addresses SHALL count as the same address for lifting when they match ignoring letter case and surrounding whitespace, with the domain in its ASCII form; for `gmail.com` and `googlemail.com` also ignoring dots before the `@` and treating both domains as one; and, only on providers known to deliver `name+tag@` to `name@` (Gmail, Outlook, Hotmail, Live, MSN, iCloud, me.com, mac.com, Fastmail, Proton), ignoring a `+tag`. When a subscription is confirmed while its mailbox is still opted out from another address, the confirm page SHALL say that the address will not get reply emails until it opts in from that other spelling, and the confirmation email SHALL NOT say that confirming turns comment emails back on.

#### Scenario: A tagged spelling on another domain cannot lift someone else's opt-out
- **WHEN** `bob@corp.example` is opted out and someone confirms a subscription for `bob+x@corp.example`
- **THEN** `bob@corp.example` stays opted out

#### Scenario: A known provider's tagged spelling lifts the opt-out
- **WHEN** `victim@gmail.com` is opted out and its owner confirms a subscription for `vic.tim+again@googlemail.com`
- **THEN** the opt-out is lifted

#### Scenario: Confirming while another spelling is opted out
- **WHEN** `bob+x@corp.example` is opted out and a subscription for `bob@corp.example` is confirmed
- **THEN** the subscription is confirmed, the page says the address won't get reply emails yet, and no reply email is sent to it

#### Scenario: A verified user's tagged address cannot lift another spelling's opt-out
- **WHEN** `bob@corp.example` is opted out and a signed-in user with the verified address `bob+x@corp.example` opts in
- **THEN** `bob@corp.example` stays opted out

## MODIFIED Requirements

### Requirement: Addresses are compared by mailbox
Wherever the site decides whether two email addresses are the same for comment emails (the per-address caps, the opt-out list, the self-reply check and the site-owner check), it SHALL compare mailboxes rather than exact strings. Two addresses SHALL be the same mailbox when they match after: ignoring letter case and surrounding whitespace; converting the domain to the ASCII form mail is delivered to (IDNA, UTS #46), so that fullwidth letters or an ideographic full stop in a domain name the same domain, and ignoring a trailing dot on the domain; dropping a `+` and everything after it from the part before the `@`; and, for the `gmail.com` and `googlemail.com` domains, also dropping dots from the part before the `@` and treating both domains as one. Comparison SHALL NOT change where an email is sent: it SHALL go to the address exactly as entered. Lifting an opt-out is the one exception: it SHALL use the stricter comparison in "Only the address that opted out can lift it".

#### Scenario: Sub-addressed spellings are one mailbox
- **WHEN** `Name+tag@Example.com` and `name@example.com` are compared
- **THEN** they are the same mailbox

#### Scenario: Gmail dots and domains are one mailbox
- **WHEN** `first.last@googlemail.com` and `firstlast@gmail.com` are compared
- **THEN** they are the same mailbox

#### Scenario: Look-alike domain spellings are one mailbox
- **WHEN** `victim@ｇmail.com` (fullwidth letter) and `victim@gmail.com` are compared
- **THEN** they are the same mailbox

#### Scenario: A trailing dot on the domain is the same mailbox
- **WHEN** `victim@gmail.com．` (trailing fullwidth full stop) and `victim@gmail.com` are compared
- **THEN** they are the same mailbox

#### Scenario: Dots matter outside Gmail
- **WHEN** `first.last@example.com` and `firstlast@example.com` are compared
- **THEN** they are different mailboxes

#### Scenario: An opt-out covers every spelling
- **WHEN** `name@example.com` is on the opt-out list and a reply is due to a subscriber at `name+blog@example.com`
- **THEN** no reply email is sent

#### Scenario: Mail goes to the address as entered
- **WHEN** a confirmation is sent for a comment whose address is `name+blog@example.com`
- **THEN** it is addressed to `name+blog@example.com`
