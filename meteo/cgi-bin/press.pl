#!/usr/bin/perl -w

use strict;
use warnings;
use CGI qw(:standard);
use CGI::Cache;
use GD;
use DBI;
use Switch 'fallthrough';
use MIME::Base64;
use Data::Dumper;


my $i = 0;
my $ii = 0;
my $x = 1500; 
my $y = 800;
my $scale_height = 30;
my $y_zoom = 30;
my $y_offset = 500;
#my $press;
my @data_arr;
my %config = ( 	ULcorr    => '+0',
		DOMcorr   => '+0',
		PRESScorr => '+0' );

my $host   = "localhost"; 
my $database = "meteo";
my $userid   = "meteo";
my $password = "meteo";

my $image = GD::Image->new($x, $y + $scale_height);
$image->interlaced('true');

my $white = $image->colorAllocate(255,255,255);
my $black = $image->colorAllocate(0,0,0);
my $red = $image->colorAllocate(255,0,0);
my $blue = $image->colorAllocate(0,0,255);
my $grey = $image->colorAllocate(120,120,120);
my $lightGrey = $image->colorAllocate(180,180,180);
my $darkGrey = $image->colorAllocate(50,50,50);
#$image->transparent($black);

my $q = CGI->new;
print $q->header(
		-type =>'text/html',
		-charset => 'utf-8',
		-Cache_control => 'no-cache, no-store, must-revalidate',
		-Pragma => 'no-cache',
		-expires => 'now'
		);
print $q->start_html(
	-title  => 'PRESSURE',
	-bgcolor  => "#101010"
);
print "\n<script>";
print "function display_Graph_data()
{
  const image = document.getElementById('Graph');
  image.addEventListener('click', function(event) {
    const x = event.offsetX;
    const y = event.offsetY;
    let gd  = \"Street: \" + graph_data[x][1] + \", home: \" + graph_data[x][2] + \", pressure: \" + graph_data[x][3];
    document.getElementById(\"H1\").textContent = `\${gd}`;
  });

}";
print "</script>\n";

my $param = $q->param('date');

print h1({style => "width: 100%; color: white; text-align: center !important;", id => "H1"} ,'Just atmosphere pressure');
#print $q->header(
#    -type    => 'image/png',
#    -expires => '+1m',
#);
CGI::Cache::stop();

my $dbh = DBI->connect("DBI:mysql:database=$database;host=$host",
                       $userid, $password,
                       {'RaiseError' => 1});

get_config();
draw_pressure_graph();

$dbh->disconnect();
print $q->end_html;


sub draw_pressure_graph
{
    my $sth;
    my $QUERY;
    my $yy;
    my @datetime_arr;
    my $position;
    my $press;

    if($param)
    {
        $QUERY = "SELECT id, tempUL, tempDOM, pressure, inserted FROM data WHERE DATE(inserted) = '$param'";
    }
    else
    {
        $QUERY = "SELECT id, tempUL, tempDOM, pressure, inserted FROM data WHERE DATE(inserted) = DATE(CURDATE())";
    }
    $sth = $dbh->prepare($QUERY);
    $sth->execute();

    $image->filledRectangle(0,0,$x,$y, $darkGrey); 			# Graph
    $image->filledRectangle(0,$y,$x,$y+$scale_height, $black); 		# Bottom scale

    while (my $ref = $sth->fetchrow_hashref())				# unpak data that SQL request returned
    {
	my $datetime = $ref->{'inserted'};
	@datetime_arr = split /[-: ]/, $datetime;
	$position = $datetime_arr[3]*60+$datetime_arr[4];
        $press = $ref->{'pressure'};					# data of pressure
	$yy = ($y-(($ref->{'pressure'} - 1000) * $y_zoom)) - $y_offset; # recalculation of stupid GD-lib area coordinates
#print STDOUT "\n<BR> $position * $i\n<br>";
#        $image->setPixel($i, $yy, $white);				# line of white pixels
        $image->setPixel($position, $yy, $white);				# line of white pixels
#        $image->line($i, $yy+1, $i, $y, $grey);				# vertical lines of graph
        $image->line($position, $yy+1, $position, $y, $grey);				# vertical lines of graph
        if($i % 60 == 0)						# scale marks
        {
	    $image->line($i, $y, $i, $y+7, $white);			# vertical lines
	    $image->string(gdMediumBoldFont, $i-3, $y+10, $ii++, $white); # digits of hours
	}
	$data_arr[$i]=[$position, $ref->{'tempUL'}, $ref->{'tempDOM'}, $press];
        $i++;
    }
    $image->filledRectangle(0,0,$x,35, $white);				# head of graph
    # FONTS: gdGiantFont, gdLargeFont, gdMediumBoldFont, gdSmallFont and gdTinyFont
    $image->string(gdGiantFont, ($x/2)-70, 10, "PRESSURE", $blue);	# 
    if($param)								# head words
    {
        $image->string(gdMediumBoldFont, 20, 10, $param, $blue);
    }
    else
    {
        $image->string(gdMediumBoldFont, 20, 10, "TODAY", $blue);
    }
    $image->string(gdMediumBoldFont, $x-70, 10, $press+$config{'PRESScorr'}, $blue);
    $sth->finish();

    # make sure we are writing to a binary stream
    #binmode STDOUT;

    # Convert the image to PNG and print it on standard output
    my $png_data = $image->png;
    my $encoded_data = encode_base64($png_data, '');		# encode my generated picture to base64 array
    my $data_uri = "data:image/png;base64," . $encoded_data;    # create an URI of my encoded picture
    print p(							# display the picture using HTML tag "img"
	img({
		src => $data_uri,
		style => "display: block; margin-left: auto; margin-right: auto;",
		onmousemove => "display_Graph_data();",
		id => "Graph"
    }));
#print Dumper(@data_arr);



print "\n<script>\nconst graph_data = [\n";
foreach my $dta (@data_arr)
 {
#print Dumper($dta);
    printf "[ %d, %.1f, %.1f, %.1f ],", @$dta[0], @$dta[1], @$dta[2], @$dta[3];
 }
print "];";

print "\n</script>\n";


}

sub get_config
{
    my $cth = $dbh->prepare("SELECT * FROM config");
    $cth->execute();

    while (my $ref_config = $cth->fetchrow_hashref())
    {
        switch( $ref_config->{'param_name'} )
        {
	    case "ULcorr" 		{ $config{'ULcorr'} = $ref_config->{'param_data'}; }
	    case "DOMcorr"		{ $config{'DOMcorr'} = $ref_config->{'param_data'}; }
	    case "PRESScorr"		{ $config{'PRESScorr'} = $ref_config->{'param_data'}; }
        }
    }
    $cth->finish();
}

sub to_minutes
{
  my ($hours, $minutes) = @_;
  return $hours * 60 + $minutes;
}