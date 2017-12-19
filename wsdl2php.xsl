<?xml version="1.0" encoding="ISO-8859-1"?>
<xsl:stylesheet version="1.0" 
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform" 
    xmlns:wsdl="http://schemas.xmlsoap.org/wsdl/"
    xmlns:xsd="http://www.w3.org/2001/XMLSchema"
    xmlns:php="http://php.net/xsl"
    xmlns:exslt="http://exslt.org/common">

<!-- Entry point template -->
<xsl:template match="/*[local-name()='definitions' and namespace-uri()='http://schemas.xmlsoap.org/wsdl/']">
    <wsdl2php>
        <services>
            <!-- select all service tags -->
            <xsl:for-each select="*[local-name()='service' and namespace-uri()='http://schemas.xmlsoap.org/wsdl/']">
                <service name="{@name}">
                    <functions>
                        <xsl:for-each select="*[local-name()='port' and namespace-uri()='http://schemas.xmlsoap.org/wsdl/']">
                            <xsl:for-each 
                                select="//*[local-name()='binding' and namespace-uri()='http://schemas.xmlsoap.org/wsdl/' and 
                                        (@name=substring-after(current()/@binding,':') or 
                                        @name=current()/@binding)]">
                                <xsl:for-each
                                        select="//*[local-name()='portType' and namespace-uri()='http://schemas.xmlsoap.org/wsdl/' and
                                            (@name=substring-after(current()/@type,':') or
                                            @name=current()/@type)]//
                                            *[local-name()='operation' and namespace-uri()='http://schemas.xmlsoap.org/wsdl/']">
                                    <function name="{@name}">
                                        <xsl:if test="//*[local-name()='message' and namespace-uri()='http://schemas.xmlsoap.org/wsdl/' and
                                                    ((@name=current()/*[local-name()='input' and namespace-uri()='http://schemas.xmlsoap.org/wsdl/']/@name) or
                                                    (@name=current()/*[local-name()='input' and namespace-uri()='http://schemas.xmlsoap.org/wsdl/']/@message) or
                                                    @name=substring-after(current()/*[local-name()='input' and namespace-uri()='http://schemas.xmlsoap.org/wsdl/']/@message,':'))]">
                                            <parameters>
    								            <xsl:apply-templates 
    								                select="//*[local-name()='message' and namespace-uri()='http://schemas.xmlsoap.org/wsdl/' and
    								                    ((@name=current()/*[local-name()='input' and namespace-uri()='http://schemas.xmlsoap.org/wsdl/']/@name) or
                                                    (@name=current()/*[local-name()='input' and namespace-uri()='http://schemas.xmlsoap.org/wsdl/']/@message) or
                                                    @name=substring-after(current()/*[local-name()='input' and namespace-uri()='http://schemas.xmlsoap.org/wsdl/']/@message,':'))]" />
    								        </parameters>
								        </xsl:if>
								        <xsl:if test="//*[local-name()='message' and namespace-uri()='http://schemas.xmlsoap.org/wsdl/' and
                                                    ((@name=current()/*[local-name()='output' and namespace-uri()='http://schemas.xmlsoap.org/wsdl/']/@name) or
                                                    (@name=current()/*[local-name()='output' and namespace-uri()='http://schemas.xmlsoap.org/wsdl/']/@message) or
                                                    @name=substring-after(current()/*[local-name()='output' and namespace-uri()='http://schemas.xmlsoap.org/wsdl/']/@message,':'))]">
    								        <returns>
    								            <xsl:apply-templates 
    								                select="//*[local-name()='message' and namespace-uri()='http://schemas.xmlsoap.org/wsdl/' and
    								                    ((@name=current()/*[local-name()='output' and namespace-uri()='http://schemas.xmlsoap.org/wsdl/']/@name) or
                                                    (@name=current()/*[local-name()='output' and namespace-uri()='http://schemas.xmlsoap.org/wsdl/']/@message) or
                                                    @name=substring-after(current()/*[local-name()='output' and namespace-uri()='http://schemas.xmlsoap.org/wsdl/']/@message,':'))]" />
    								        </returns>
								        </xsl:if>
								        <xsl:if test="//*[local-name()='message' and namespace-uri()='http://schemas.xmlsoap.org/wsdl/' and
                                                    ((@name=current()/*[local-name()='fault' and namespace-uri()='http://schemas.xmlsoap.org/wsdl/']/@name) or
                                                    (@name=current()/*[local-name()='fault' and namespace-uri()='http://schemas.xmlsoap.org/wsdl/']/@message) or
                                                    @name=substring-after(current()/*[local-name()='fault' and namespace-uri()='http://schemas.xmlsoap.org/wsdl/']/@message,':'))]">
    								        <exceptions>
    	   							            <xsl:apply-templates 
    	   	  						                select="//*[local-name()='message' and namespace-uri()='http://schemas.xmlsoap.org/wsdl/' and
    			 					                    ((@name=current()/*[local-name()='fault' and namespace-uri()='http://schemas.xmlsoap.org/wsdl/']/@name) or
                                                    (@name=current()/*[local-name()='fault' and namespace-uri()='http://schemas.xmlsoap.org/wsdl/']/@message) or
                                                    @name=substring-after(current()/*[local-name()='fault' and namespace-uri()='http://schemas.xmlsoap.org/wsdl/']/@message,':'))]" />
    			 					        </exceptions>
    			 					    </xsl:if>
								    </function>                                        
                                </xsl:for-each>
                            </xsl:for-each>
                        </xsl:for-each>
                    </functions>
                </service>
            </xsl:for-each>
        </services>
        <classes>
            <!-- select all types/complexType or types/simpleType nodes unless the tag name starts with ArrayOf -->
            <xsl:for-each select="*[local-name()='types' and namespace-uri()='http://schemas.xmlsoap.org/wsdl/']
                    //*[(local-name()='complexType' or local-name()='simpleType') and 
                        not(starts-with(@name, 'ArrayOf'))]">
                <class name="{@name | ../@name}">
                    <!-- check if any descendant node has the tag name 'extension' -->
                    <xsl:if test=".//*[local-name()='extension']">
                        <extends>
                            <xsl:choose>
                                <!-- and get the value of the base attribute (strip namespace) -->
                                <xsl:when test="contains(.//*[local-name()='extension']/@base,':')">
                                    <xsl:value-of select="substring-after(.//*[local-name()='extension']/@base,':')" />
                                </xsl:when>
                                <xsl:otherwise>
                                    <xsl:value-of select=".//*[local-name()='extension']/@base" />
                                </xsl:otherwise>
                            </xsl:choose>
                        </extends>
                    </xsl:if>
                    <!-- are there any descendant element or attribute tags? -->
                    <xsl:if test=".//*[local-name()='element'] or .//*[local-name()='attribute']">
                        <!-- yes, so lets create properties from element or attribute tags -->
                        <properties>
                            <xsl:apply-templates select=".//*[local-name()='element']" />
                            <xsl:apply-templates select=".//*[local-name()='attribute']" />
                        </properties>
                    </xsl:if>
                </class>
            </xsl:for-each>
        </classes>
    </wsdl2php>
</xsl:template>

<!-- 
  handle message tags 
  
  This is called from the 'services'.
  Basically this will generate the method definitions
-->
<xsl:template match="*[local-name()='message' and namespace-uri()='http://schemas.xmlsoap.org/wsdl/']">
    <xsl:for-each select="*[local-name()='part' and namespace-uri()='http://schemas.xmlsoap.org/wsdl/']">
        <xsl:choose>
            <xsl:when test="@element">
                <xsl:call-template name="messagePart">
                    <xsl:with-param name="refnode" select="current()" />
                    <xsl:with-param name="name" select="@name" />
                    <xsl:with-param name="type" select="@element" />
                </xsl:call-template>
            </xsl:when>
            <xsl:when test="@type">
                <xsl:call-template name="messagePart">
                    <xsl:with-param name="refnode" select="current()" />
                    <xsl:with-param name="name" select="@name" />
                    <xsl:with-param name="type" select="@type" />
                </xsl:call-template>
            </xsl:when>
            <xsl:otherwise>
                <xsl:call-template name="messagePart">
                    <xsl:with-param name="refnode" select="current()" />
                    <xsl:with-param name="name" select="@name" />
                </xsl:call-template>
            </xsl:otherwise>
        </xsl:choose>
    </xsl:for-each>
</xsl:template>

<!-- 
  handle element tags (in types)
  
  Unfortunately this template does not know the context of an element, it
  matches elements in local complexType definitions, but i don't know how to prevent this
-->
<xsl:template match="*[local-name()='element' and namespace-uri()='http://www.w3.org/2001/XMLSchema']">
    <xsl:choose>
        <xsl:when test="@ref and @name">
            <xsl:call-template name="messagePart">
                <xsl:with-param name="refnode" select="current()" />
                <xsl:with-param name="name" select="@name" />
                <xsl:with-param name="type" select="@ref" />
            </xsl:call-template>
        </xsl:when>
        <xsl:when test="@ref and contains(@ref,':')">
            <xsl:call-template name="messagePart">
                <xsl:with-param name="refnode" select="current()" />
                <xsl:with-param name="name" select="substring-after(@ref,':')" />
                <xsl:with-param name="type" select="@ref" />
            </xsl:call-template>
        </xsl:when>
        <xsl:when test="@ref">
            <xsl:call-template name="messagePart">
                <xsl:with-param name="refnode" select="current()" />
                <xsl:with-param name="name" select="@ref" />
                <xsl:with-param name="type" select="@ref" />
            </xsl:call-template>
        </xsl:when>
        <xsl:when test="@type">
            <xsl:call-template name="messagePart">
                <xsl:with-param name="refnode" select="current()" />
                <xsl:with-param name="name" select="@name" />
                <xsl:with-param name="type" select="@type" />
            </xsl:call-template>
        </xsl:when>
        <xsl:otherwise>
            <xsl:call-template name="messagePart">
                <xsl:with-param name="refnode" select="current()" />
                <xsl:with-param name="name" select="@name" />
            </xsl:call-template>
        </xsl:otherwise>
    </xsl:choose>
</xsl:template>

<!-- 
  handle attribute tags (in types)
  
  Unfortunately this template does not know the context of an attribute
-->
<xsl:template match="*[local-name()='attribute' and namespace-uri()='http://www.w3.org/2001/XMLSchema']">
    <xsl:choose>
        <xsl:when test="@type and @name">
            <xsl:call-template name="messagePart">
                <xsl:with-param name="refnode" select="current()" />
                <xsl:with-param name="name" select="@name" />
                <xsl:with-param name="type" select="@type" />
            </xsl:call-template>
        </xsl:when>
        <xsl:otherwise>
            <xsl:call-template name="messagePart">
                <xsl:with-param name="refnode" select="current()" />
                <xsl:with-param name="name" select="@name" />
            </xsl:call-template>
        </xsl:otherwise>
    </xsl:choose>
</xsl:template>

<!-- handles types (add entry name="" type="" tags, used in buth function/parameter and class/properties declarations) -->
<xsl:template name="messagePart">
    <xsl:param name="refnode" />
    <xsl:param name="name" />
    <xsl:param name="type" />
    <xsl:variable name="arraytype">
      <xsl:choose>
          <xsl:when test="$refnode[@maxOccurs='unbounded']">[]</xsl:when>
          <xsl:otherwise></xsl:otherwise>
      </xsl:choose>
    </xsl:variable>
    <xsl:choose>
        <!-- no type specified -->
        <!-- type is optional, so this is valid, should be handled as direct type declaration? -->
        <xsl:when test="string-length($type)=0">
            <!-- do we have a nested complextype? -->
            <xsl:choose>
                <!-- is there exactly one element in the complexType? -->
                <xsl:when test="count($refnode
                                /*[local-name()='complexType' and namespace-uri()='http://www.w3.org/2001/XMLSchema']
                                /*[local-name()='sequence' and namespace-uri()='http://www.w3.org/2001/XMLSchema']
                                /*[local-name()='element' and namespace-uri()='http://www.w3.org/2001/XMLSchema' and (@type or @ref) ] )=1">
                    <xsl:variable name="matchedNode" select="$refnode
                                /*[local-name()='complexType' and namespace-uri()='http://www.w3.org/2001/XMLSchema']
                                /*[local-name()='sequence' and namespace-uri()='http://www.w3.org/2001/XMLSchema']
                                /*[local-name()='element' and namespace-uri()='http://www.w3.org/2001/XMLSchema'][1]" />
                    <xsl:variable name="subArraytype">
                      <xsl:choose>
                          <xsl:when test="$refnode[@maxOccurs='unbounded']">[]</xsl:when>
                          <xsl:when test="$matchedNode[@maxOccurs='unbounded']">[]</xsl:when>
                          <xsl:otherwise></xsl:otherwise>
                      </xsl:choose>
                    </xsl:variable>
                    <xsl:call-template name="writeEntry">
                        <xsl:with-param name="debug">1b</xsl:with-param>
                        <xsl:with-param name="name"><xsl:value-of select="$name"/></xsl:with-param>
                        <xsl:with-param name="type"><xsl:choose>
                                <xsl:when test="$matchedNode[@type] and contains($matchedNode/@type,':')"><xsl:value-of select="substring-after($matchedNode/@type,':')"/></xsl:when>
                                <xsl:when test="$matchedNode[@type]"><xsl:value-of select="$matchedNode/@type"/></xsl:when>
                                <xsl:when test="$matchedNode[@ref] and contains($matchedNode/@ref,':')"><xsl:value-of select="substring-after($matchedNode/@ref,':')"/></xsl:when>
                                <xsl:when test="$matchedNode[@ref]"><xsl:value-of select="$matchedNode/@ref"/></xsl:when>
                            </xsl:choose><xsl:value-of select="$subArraytype"/></xsl:with-param>
                    </xsl:call-template>
                </xsl:when>
                <!-- currently we cannot handle unnamed complex types which are actually complex (more than one element) 
                     It would require a new type/class definition with a made up name, but we couldn't handle those anyway
                -->
                <xsl:otherwise>
                    <xsl:call-template name="writeEntry">
                        <xsl:with-param name="debug">1</xsl:with-param>
                        <xsl:with-param name="name"><xsl:value-of select="$name"/></xsl:with-param>
                        <xsl:with-param name="type">anyType<xsl:value-of select="$arraytype"/></xsl:with-param>
                        <xsl:with-param name="error">no type or element in message</xsl:with-param>
                    </xsl:call-template>
                </xsl:otherwise>
            </xsl:choose>
        </xsl:when>
        <!-- base xsd types -->
        <xsl:when test="(substring-before($type,':')='xsd') or (substring-before($type,':')='xs')">
          <xsl:call-template name="writeEntry">
              <xsl:with-param name="debug">2</xsl:with-param>
              <xsl:with-param name="name"><xsl:value-of select="$name"/></xsl:with-param>
              <xsl:with-param name="type"><xsl:value-of select="substring-after($type,':')"/><xsl:value-of select="$arraytype"/></xsl:with-param>
          </xsl:call-template>
        </xsl:when>
        <!-- check if there is a element tag directly in schema whose name matches the $type (ignoring namespace) -->
        <xsl:when test="//*[local-name()='schema' and namespace-uri()='http://www.w3.org/2001/XMLSchema']
                        /*[local-name()='element' and namespace-uri()='http://www.w3.org/2001/XMLSchema' 
                        and (@name=substring-after($type,':') or @name=$type)]">
            <xsl:variable name="matchedNode" select="//*[local-name()='schema' and namespace-uri()='http://www.w3.org/2001/XMLSchema']
                        /*[local-name()='element' and namespace-uri()='http://www.w3.org/2001/XMLSchema' 
                        and (@name=substring-after($type,':') or @name=$type)]" />
            <xsl:choose>
                <!-- does the element have a type attribute? -->
                <xsl:when test="$matchedNode/@type">
                    <xsl:choose>
                        <!-- does the type attribute have a namespace? -->
                        <xsl:when test="contains($matchedNode/@type,':')">
                          <xsl:call-template name="writeEntry">
                              <xsl:with-param name="debug">3</xsl:with-param>
                              <xsl:with-param name="name"><xsl:value-of select="$name"/></xsl:with-param>
                              <xsl:with-param name="type"><xsl:value-of select="substring-after($matchedNode/@type,':')"/><xsl:value-of select="$arraytype"/></xsl:with-param>
                          </xsl:call-template>
                        </xsl:when>
                        <xsl:otherwise>
                          <xsl:call-template name="writeEntry">
                              <xsl:with-param name="debug">3b</xsl:with-param>
                              <xsl:with-param name="name"><xsl:value-of select="$name"/></xsl:with-param>
                              <xsl:with-param name="type"><xsl:value-of select="$matchedNode/@type"/><xsl:value-of select="$arraytype"/></xsl:with-param>
                          </xsl:call-template>
                        </xsl:otherwise>
                    </xsl:choose>
                </xsl:when>
                <!-- or else does the element have a name attribute? -->
                <xsl:when test="$matchedNode/@name">
                    <xsl:call-template name="writeEntry">
                        <xsl:with-param name="debug">3c</xsl:with-param>
                        <xsl:with-param name="name"><xsl:value-of select="$name"/></xsl:with-param>
                        <xsl:with-param name="type"><xsl:value-of select="$matchedNode/@name"/><xsl:value-of select="$arraytype"/></xsl:with-param>
                    </xsl:call-template>
                </xsl:when>
                <!-- or else (no name or type attribute, bad situation) -->
                <xsl:otherwise>
                    <xsl:call-template name="writeEntry">
                        <xsl:with-param name="debug">3d</xsl:with-param>
                        <xsl:with-param name="name"><xsl:value-of select="$name"/></xsl:with-param>
                        <xsl:with-param name="type"><xsl:value-of select="$name"/><xsl:value-of select="$arraytype"/></xsl:with-param>
                        <xsl:with-param name="error">bad element type def</xsl:with-param>
                    </xsl:call-template>
                </xsl:otherwise>
            </xsl:choose>
        </xsl:when>
        <!-- check if there is a complexType tag directly in schema whose name matches the $type (ignoring namespace) and who has descendants with the ref='soapenc:arrayType' attribute -->
        <xsl:when test="//*[local-name()='schema' and namespace-uri()='http://www.w3.org/2001/XMLSchema']
                        /*[local-name()='complexType' and namespace-uri()='http://www.w3.org/2001/XMLSchema' 
                        and (@name=substring-after($type,':') or @name=$type)]//*[@ref='soapenc:arrayType']">
            <xsl:variable name="typeref" select="//*[local-name()='schema' and 
                        namespace-uri()='http://www.w3.org/2001/XMLSchema']
                        /*[local-name()='complexType' and namespace-uri()='http://www.w3.org/2001/XMLSchema' 
                        and (@name=substring-after($type,':') or @name=$type)]//*[@ref='soapenc:arrayType']
                        /@*[local-name()='arrayType']" />
            <xsl:choose>
                <xsl:when test="contains($typeref,':')">
                    <xsl:call-template name="writeEntry">
                        <xsl:with-param name="debug">4</xsl:with-param>
                        <xsl:with-param name="name"><xsl:value-of select="$name"/></xsl:with-param>
                        <xsl:with-param name="type"><xsl:value-of select="substring-after($typeref,':')"/></xsl:with-param>
                    </xsl:call-template>
                </xsl:when>
                <xsl:otherwise>
                    <xsl:call-template name="writeEntry">
                        <xsl:with-param name="debug">4b</xsl:with-param>
                        <xsl:with-param name="name"><xsl:value-of select="$name"/></xsl:with-param>
                        <xsl:with-param name="type"><xsl:value-of select="$typeref"/></xsl:with-param>
                    </xsl:call-template>
                </xsl:otherwise>
            </xsl:choose>
        </xsl:when>
        <!-- check if there is a complexType tag directly in schema whose name matches the $type (must have namespace) -->
        <xsl:when test="//*[local-name()='schema' and namespace-uri()='http://www.w3.org/2001/XMLSchema']
                        /*[local-name()='complexType' and namespace-uri()='http://www.w3.org/2001/XMLSchema' 
                        and @name=substring-after($type,':')]">
            <xsl:variable name="typeref" select="//*[local-name()='schema' and 
                        namespace-uri()='http://www.w3.org/2001/XMLSchema']
                        /*[local-name()='complexType' and namespace-uri()='http://www.w3.org/2001/XMLSchema' 
                        and (@name=substring-after($type,':') or @name=$type)]/@name" />
            <xsl:choose>
                <xsl:when test="contains($typeref,':')">
                    <xsl:call-template name="writeEntry">
                        <xsl:with-param name="debug">5</xsl:with-param>
                        <xsl:with-param name="name"><xsl:value-of select="$name"/></xsl:with-param>
                        <xsl:with-param name="type"><xsl:value-of select="substring-after($typeref,':')"/><xsl:value-of select="$arraytype"/></xsl:with-param>
                    </xsl:call-template>
                </xsl:when>
                <xsl:otherwise>
                    <xsl:call-template name="writeEntry">
                        <xsl:with-param name="debug">5b</xsl:with-param>
                        <xsl:with-param name="name"><xsl:value-of select="$name"/></xsl:with-param>
                        <xsl:with-param name="type"><xsl:value-of select="$typeref"/><xsl:value-of select="$arraytype"/></xsl:with-param>
                    </xsl:call-template>
                </xsl:otherwise>
            </xsl:choose>                        
        </xsl:when>
        <!-- if none of the above matches -->
        <xsl:otherwise>
            <xsl:call-template name="writeEntry">
                <xsl:with-param name="debug">6</xsl:with-param>
                <xsl:with-param name="name"><xsl:value-of select="$name"/></xsl:with-param>
                <xsl:with-param name="type"><xsl:value-of select="$type"/><xsl:value-of select="$arraytype"/></xsl:with-param>
                <xsl:with-param name="error">uninterpreted</xsl:with-param>
                <xsl:with-param name="fname"><xsl:value-of select="name($refnode)"/></xsl:with-param>
                <xsl:with-param name="lname"><xsl:value-of select="local-name($refnode)"/></xsl:with-param>
                <xsl:with-param name="uri"><xsl:value-of select="namespace-uri($refnode)"/></xsl:with-param>
            </xsl:call-template>
        </xsl:otherwise>
    </xsl:choose>
</xsl:template>

<!-- write the entry tag -->
<xsl:template name="writeEntry">
    <xsl:param name="debug" />
    <xsl:param name="name" />
    <xsl:param name="type" />
    <!-- optional attributes -->
    <xsl:param name="error" />
    <xsl:param name="fname" />
    <xsl:param name="lname" />
    <xsl:param name="uri" />
    <xsl:element name="entry">
      <xsl:attribute name="debug"><xsl:value-of select="$debug"/></xsl:attribute>
      <xsl:attribute name="name"><xsl:value-of select="$name"/></xsl:attribute>
      <xsl:attribute name="type"><xsl:value-of select="$type"/></xsl:attribute>
      <xsl:if test="string-length($error) &gt; 0">
         <xsl:attribute name="error"><xsl:value-of select="$error"/></xsl:attribute>
      </xsl:if>
      <xsl:if test="string-length($fname) &gt; 0">
         <xsl:attribute name="fname"><xsl:value-of select="$fname"/></xsl:attribute>
      </xsl:if>
      <xsl:if test="string-length($lname) &gt; 0">
         <xsl:attribute name="lname"><xsl:value-of select="$lname"/></xsl:attribute>
      </xsl:if>
      <xsl:if test="string-length($uri) &gt; 0">
         <xsl:attribute name="uri"><xsl:value-of select="$uri"/></xsl:attribute>
      </xsl:if>
    </xsl:element>
</xsl:template>

</xsl:stylesheet>
