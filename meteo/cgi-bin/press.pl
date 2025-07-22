#!/usr/bin/perl -w

use strict;
use warnings;
use CGI qw(:standard);
use CGI::Cache;
use GD;
use DBI;
use Switch 'fallthrough';
use MIME::Base64;

my $i=0;
my $x = 1500; 
my $y = 800;
my $y_zoom = 30;
my $y_offset = 500;
my $press;
my %config = ( 	ULcorr    => '+0',
		DOMcorr   => '+0',
		PRESScorr => '+0' );

my $host   = "localhost"; 
my $database = "DB";
my $userid   = "user";
my $password = "pass";

#print "Content-type: image/png\n\n";

my $image = GD::Image->new($x, $y);

my $white = $image->colorAllocate(255,255,255);
my $black = $image->colorAllocate(0,0,0);
my $red = $image->colorAllocate(255,0,0);
my $blue = $image->colorAllocate(0,0,255);
my $grey = $image->colorAllocate(120,120,120);
my $lightGrey = $image->colorAllocate(180,180,180);
my $darkGrey = $image->colorAllocate(50,50,50);

my $q = CGI->new;
print $q->header('text/html; charset=utf-8');
#print $q->start_html('PRESSURE');
print $q->start_html(
	-title  => 'PRESSURE',
	-bgcolor  => "#101010"
);

print h1({style => "color: white;"}, 'Добро пожаловать на мой сайт!');
#print $q->header(
#    -type    => 'image/png',
#    -expires => '+1m',
#);
CGI::Cache::stop();

my $dbh = DBI->connect("DBI:mysql:database=$database;host=$host",
                       $userid, $password,
                       {'RaiseError' => 1});

my $sth;
my $QUERY;
my $param = $q->param('date');
if($param)
{
    $QUERY = "SELECT id, pressure FROM data WHERE DATE(inserted) = '$param'";
}
else
{
    $QUERY = "SELECT id, pressure FROM data WHERE DATE(inserted) = DATE(CURDATE())";
}
$sth = $dbh->prepare($QUERY);
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

$image->transparent($black);
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
# FONTS: gdGiantFont, gdLargeFont, gdMediumBoldFont, gdSmallFont and gdTinyFont
$image->string(gdGiantFont, ($x/2)-70, 10, "PRESSURE", $blue);
if($param)
{
    $image->string(gdMediumBoldFont, 20, 10, $param, $blue);
}
else
{
    $image->string(gdMediumBoldFont, 20, 10, "TODAY", $blue);

}
$image->string(gdMediumBoldFont, $x-70, 10, $press+$config{'PRESScorr'}, $blue);

$sth->finish();
$dbh->disconnect();

# make sure we are writing to a binary stream
binmode STDOUT;
# Convert the image to PNG and print it on standard output

my $png_data = $image->png;
my $encoded_data = encode_base64($png_data, '');
my $data_uri = "data:image/png;base64," . $encoded_data;
print p(
    img({
	    src => $data_uri,
	    style => "display: block; margin-left: auto; margin-right: auto;"
    })
);


print $q->end_html;

