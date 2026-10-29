import {Trans} from '@ui/i18n/trans';
import {CalendarTodayIcon} from '@ui/icons/material/CalendarToday';
import {PlaceIcon} from '@ui/icons/material/Place';
import {ConfirmationNumberIcon} from '@ui/icons/material/ConfirmationNumber';
import {Button} from '@ui/buttons/button';
import {Link} from 'react-router';

export function Component() {
  const events = [
    {
      id: 1,
      artist: 'David Guetta',
      title: 'Monolith Live World Tour',
      location: 'Accor Arena, Paris',
      date: 'Dec 12, 2026',
      badge: 'Headline',
    },
    {
      id: 2,
      artist: 'The Weeknd',
      title: 'After Hours Till Dawn',
      location: 'Wembley Stadium, London',
      date: 'Jan 24, 2027',
      badge: 'Sold Out Soon',
    },
    {
      id: 3,
      artist: 'PNL',
      title: 'Deux Frères Experience',
      location: 'Dôme de Marseille, France',
      date: 'Feb 18, 2027',
      badge: 'Exclusive',
    },
    {
      id: 4,
      artist: 'Ambre Ballester & AI Hybrid Ensemble',
      title: 'Future Sounds Live Showcase',
      location: 'Electric Ballroom, London',
      date: 'March 05, 2027',
      badge: 'TuneMusics Live',
    },
  ];

  return (
    <div className="min-h-full p-16 md:p-32 max-w-5xl mx-auto">
      <div className="mb-24 pb-16 border-b border-divider">
        <h1 className="text-3xl font-extrabold tracking-tight mb-8">
          <Trans message="Concerts & Live Events" />
        </h1>
        <p className="text-muted text-sm">
          <Trans message="Discover live performances, stadium tours, and hybrid festivals from your favorite TuneMusics artists." />
        </p>
      </div>

      <div className="grid gap-16 md:grid-cols-2">
        {events.map(ev => (
          <div
            key={ev.id}
            className="p-20 rounded-panel bg-elevated border border-divider hover:border-primary transition duration-200 flex flex-col justify-between"
          >
            <div>
              <div className="flex items-center justify-between mb-8">
                <span className="text-xs font-bold uppercase text-primary tracking-wide">
                  {ev.badge}
                </span>
                <span className="text-xs text-muted flex items-center gap-4">
                  <CalendarTodayIcon size="sm" />
                  {ev.date}
                </span>
              </div>
              <h2 className="text-xl font-bold mb-4">{ev.title}</h2>
              <p className="text-sm font-semibold text-main mb-8">{ev.artist}</p>
              <p className="text-xs text-muted flex items-center gap-6 mb-16">
                <PlaceIcon size="sm" />
                {ev.location}
              </p>
            </div>

            <div className="pt-12 border-t border-divider/50 flex items-center justify-between">
              <Button
                variant="flat"
                color="primary"
                size="sm"
                startIcon={<ConfirmationNumberIcon />}
                elementType={Link}
                to="/popular-artists"
              >
                <Trans message="View Artist & Tickets" />
              </Button>
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}
