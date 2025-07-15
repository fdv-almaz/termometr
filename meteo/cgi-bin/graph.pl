#!/usr/bin/perl -w

use strict;
use warnings;
use CGI;
use GD;
use DBI;
use Switch 'fallthrough';

my $i=0;
my $x = 1700;
my $y = 500;
my $y_zoom = 40;
my $y_offset = 50;
my $press;
my %config = ( 	ULcorr    => '+0',
		DOMcorr   => '+0',
		PRESScorr => '+0' );

my $host   = "localhost"; 
my $database = "database";
my $userid   = "username";
my $password = "password";

print "Content-type: image/png\n\n";

my $image = GD::Image->new($x, $y);

my $white = $image->colorAllocate(255,255,255);
my $black = $image->colorAllocate(0,0,0);
my $red = $image->colorAllocate(255,0,0);
my $blue = $image->colorAllocate(0,0,255);
my $grey = $image->colorAllocate(120,120,120);
my $lightGrey = $image->colorAllocate(180,180,180);
my $darkGrey = $image->colorAllocate(50,50,50);


my $dbh = DBI->connect("DBI:mysql:database=$database;host=$host",
                       $userid, $password,
                       {'RaiseError' => 1});

my $sth = $dbh->prepare("SELECT id, pressure FROM data WHERE DATE(inserted) = DATE(CURDATE())");
$sth->execute();
my $cth = $dbh->prepare("SELECT * FROM config");
$cth->execute();

while (my $ref_config = $cth->fetchrow_hashref())
{
    switch( $ref_config->{'param_name'} )
    {
	case "ULcorr" 		{ $config{'ULcorr'} = $ref_config->{'param_data'}; }
	case "DOMcorr"		{ $config{'DOMcorr'} = $ref_config->{'param_data'}; }
	case "PRESScorr"	{ $config{'PRESScorr'} = $ref_config->{'param_data'}; }
    }
}

$image->transparent($white);
$image->fill(50,50,$darkGrey);
$image->interlaced('true');

while (my $ref = $sth->fetchrow_hashref())
{
    $press = $ref->{'pressure'};
    my $yy = ($y-(($ref->{'pressure'} - 1000) * $y_zoom)) - $y_offset;
    $image->setPixel($i, $yy, $white);
    $image->line($i, $yy+1, $i, $y, $grey);
    $i++;
}

$image->filledRectangle(0,0,$x,35, $white);
# gdGiantFont, gdLargeFont, gdMediumBoldFont, gdSmallFont and gdTinyFont
$image->string(gdGiantFont, ($x/2)-70, 10, "PRESSURE", $blue);
$image->string(gdMediumBoldFont, $x-70, 10, $press+$config{'PRESScorr'}, $blue);

$sth->finish();
$dbh->disconnect();

# make sure we are writing to a binary stream
binmode STDOUT;
# Convert the image to PNG and print it on standard output
print $image->png;
